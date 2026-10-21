<?php

namespace Modules\SharePointDocs\Services;

use App\Models\Notification\UserNotification;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use App\Services\Language;
use App\Services\Notifications\Notifier;
use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\SharePointDocs\Models\EditRequest;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;
use Throwable;

/**
 * Changing a cell of a SharePoint workbook, in two steps and by two people.
 *
 *  1. submit(): somebody who may change the workbook asks for a change. Nothing is written; the request is kept,
 *     with what the cell and its row held at that moment.
 *  2. approve(): somebody else who may approve says yes. Only now is SharePoint asked: the workbook is read as it is
 *     NOW, and written only if the cell, and the row it is in, are still what they were when the change was asked
 *     for, and if nobody has saved the file in the meantime. Otherwise nothing is written and the request says why
 *     (CONFLICT, or FAILED when SharePoint could not be written to, which can be tried again).
 *
 * Who may ask and who may approve is not decided here: it is the menu of each page (Update on the workbook's
 * menu, Update on the menu "Persetujuan Edit"), checked where the request comes in. What is decided here is what
 * the menu cannot say: nobody approves their own request, and a cell that holds a formula is not written over.
 * Every request that is made, approved or turned down is written to the audit trail from here, wherever it came from
 * (the pages, or the API).
 *
 * What is refused is said in the language of the request (SharePointDocs::edits). Why an approved change was not written is
 * stored with the request in Indonesian, as everything the application writes down, and said in their own language to the
 * approver (EditRequest::failureIn()) and in the notification to whoever asked.
 */
class CellEdits
{
    /** The longest text a request may carry (an Excel cell holds more, a register hardly ever needs it). */
    public const MAX_LENGTH = 1000;

    /** A request that has been "processing" this long is taken for one whose process died, and can be taken up again (minutes). */
    private const STUCK_AFTER = 5;

    public function __construct(private readonly SharePointWorkbook $workbooks, private readonly XlsxPatcher $patcher)
    {
    }

    /**
     * Whether the table of the requests is there. A database that has not been migrated yet has none (the code of this
     * folder is live as soon as it is saved, the migration is run by hand): until then the pages do not offer changing
     * anything, instead of failing. (Asked once a minute, not with every page.)
     */
    public static function ready(): bool
    {
        return Cache::remember('sharepoint-edit-requests-table', 60, fn () => Schema::hasTable((new EditRequest())->getTable()));
    }

    /** Whether the column that marks a change written straight away is there (its migration is run by hand). Asked once a minute. */
    public static function directReady(): bool
    {
        return self::ready() && Cache::remember('sharepoint-edit-direct-column', 60, fn () => Schema::hasColumn((new EditRequest())->getTable(), 'direct'));
    }

    /**
     * Whether $user may write cells of the workbook straight away, without somebody else approving it (K13h): a person
     * (not an API client) who may ask for changes of the workbook (Update on its menu) and approve them (Update on
     * "Persetujuan Edit"): the Document Controller, Super Admin. For them the rule of two people does not hold; SharePoint
     * names the application as the one who saved, the audit trail names them.
     */
    public static function mayWriteDirectly(?User $user, string $workbook): bool
    {
        return $user !== null && ! $user->isApiClient() && self::enabled($workbook) && self::directReady()
            && MenuAccess::allows($user, "documents.{$workbook}.edit", 'Update')
            && MenuAccess::allows($user, 'documents.edit-approvals.approve', 'Update');
    }

    /** Whether the workbook may be changed from here at all (config "edit.enabled", and the table of the requests being there). */
    public static function enabled(string $workbook): bool
    {
        return is_array(config("sharepoint-docs.workbooks.{$workbook}"))
            && (bool) config("sharepoint-docs.workbooks.{$workbook}.edit.enabled", true)
            && self::ready();
    }

    /**
     * Asks for a change of one cell, as the page shows it: the sheet by its position, the row by its number in Excel
     * and the column by its position in the table.
     *
     * @throws EditException the change is refused (what is wrong is said in words)
     * @throws GraphException the workbook could not be read
     */
    public function submit(User $user, string $workbook, int $sheetIndex, int $line, int $column, string $value): EditRequest
    {
        if (! self::ready()) {
            throw new EditException(__('SharePointDocs::edits.not_ready'), 503);
        }
        if (! self::enabled($workbook)) {
            throw new EditException(__('SharePointDocs::edits.disabled'), 403);
        }

        $book = $this->workbooks->tables($workbook);
        if ($book['stale']) {
            throw new EditException(__('SharePointDocs::edits.stale'), 503);
        }
        $sheet = $book['sheets'][$sheetIndex] ?? null;
        if ($sheet === null) {
            throw new EditException(__('SharePointDocs::edits.no_sheet'));
        }
        $row = array_search($line, $sheet['lines'], true);
        if ($row === false) {
            throw new EditException(__('SharePointDocs::edits.no_row'));
        }
        if ($column < 0 || ! isset($sheet['positions'][$column])) {
            throw new EditException(__('SharePointDocs::edits.column_fixed'));
        }
        if (isset($sheet['formulas'][$row][$column])) {
            throw new EditException(__('SharePointDocs::edits.formula'));
        }

        $oldText = (string) ($sheet['rows'][$row][$column] ?? '');
        $oldNumber = isset($sheet['numbers'][$row][$column]) ? (float) $sheet['numbers'][$row][$column] : null;
        [$kind, $text, $number] = self::interpret($value, $oldText, $oldNumber);
        if (self::same($kind, $text, $number, $oldText, $oldNumber)) {
            throw new EditException(__('SharePointDocs::edits.same'));
        }

        $anchor = $this->anchor($workbook, $sheet);
        $cell = XlsxPatcher::columnLetters($sheet['positions'][$column]) . $line;

        $waiting = EditRequest::query()
            ->where('workbook', $workbook)->where('sheet', $sheet['name'])->where('cell', $cell)
            ->whereIn('status', [EditRequest::PENDING, EditRequest::PROCESSING])
            ->first();
        if ($waiting !== null) {
            throw new EditException(__('SharePointDocs::edits.waiting', ['cell' => $cell, 'name' => $waiting->requested_by_name]), 409);
        }

        $made = EditRequest::create([
            'workbook' => $workbook,
            'sheet' => $sheet['name'],
            'cell' => $cell,
            'line' => $line,
            'column_title' => mb_substr((string) ($sheet['columns'][$column] ?? ''), 0, 200),
            'anchor_cell' => $anchor === null ? null : XlsxPatcher::columnLetters($sheet['positions'][$anchor]) . $line,
            'anchor_value' => $anchor === null ? null : mb_substr((string) ($sheet['rows'][$row][$anchor] ?? ''), 0, 500),
            'old_text' => mb_substr($oldText, 0, 2000),
            'old_number' => self::stored($oldNumber),
            'new_kind' => $kind,
            'new_value' => $kind === EditRequest::KIND_EMPTY ? null : $text,
            'new_number' => self::stored($number),
            'status' => EditRequest::PENDING,
            'requested_by' => $user->id,
            'requested_by_name' => mb_substr((string) $user->name, 0, 255),
        ]);
        app(AuditLogger::class)->log(AuditLogger::EDIT_REQUESTED, $made, ['Value' => $made->oldShown()],
            ['Value' => $made->newShown()] + array_filter(['Column' => $made->column_title]), $user, $made->auditLabel());
        $this->tellApprovers($made, $user);

        return $made;
    }

    /**
     * Writes an approved change to the workbook. The request that comes back has its final status: applied, conflict
     * or failed (rejected is for reject()). Nothing is thrown for what happens to the workbook; it is the status.
     *
     * @throws EditException the request cannot be decided on: it is the approver's own, or already decided
     */
    public function approve(EditRequest $request, User $approver): EditRequest
    {
        if ((int) $request->requested_by === (int) $approver->id) {
            throw new EditException(__('SharePointDocs::edits.own'), 403);
        }

        // One approver at a time: the first one to change the status is the one who writes.
        $claimed = EditRequest::query()->whereKey($request->id)
            ->where(fn ($query) => $query
                ->whereIn('status', [EditRequest::PENDING, EditRequest::FAILED])
                ->orWhere(fn ($stuck) => $stuck->where('status', EditRequest::PROCESSING)->where('updated_at', '<', now()->subMinutes(self::STUCK_AFTER))))
            ->update([
                'status' => EditRequest::PROCESSING,
                'decided_by' => $approver->id,
                'decided_by_name' => mb_substr((string) $approver->name, 0, 255),
                'decided_at' => now(),
                'decision_note' => null,
                'failure' => null,
                'updated_at' => now(),
            ]);
        if ($claimed !== 1) {
            throw new EditException(__('SharePointDocs::edits.taken'), 409);
        }
        $request->refresh();

        // Reading the workbook and writing it each have the time of a download (preview_time_limit): together they may take more than PHP's default.
        if (function_exists('set_time_limit')) {
            @set_time_limit(max(60, 3 * (int) config('sharepoint-docs.preview_time_limit', 25)));
        }

        try {
            [$status, $failure] = $this->write($request);
        } catch (Throwable $e) {
            Log::error('A change of a SharePoint workbook failed unexpectedly.', ['request' => $request->id, 'exception' => $e]);
            [$status, $failure] = [EditRequest::FAILED, self::words('app_error')];
        }

        $request->forceFill([
            'status' => $status,
            'failure' => $failure === null ? null : mb_substr($failure(Language::DEFAULT), 0, 500),
            'applied_at' => $status === EditRequest::APPLIED ? now() : null,
        ])->save();
        $request->failureWords = $failure;
        Log::info('A change of a SharePoint workbook was decided.', [
            'request' => $request->id, 'workbook' => $request->workbook, 'sheet' => $request->sheet, 'cell' => $request->cell,
            'status' => $status, 'requested_by' => $request->requested_by, 'decided_by' => $approver->id,
        ]);
        // Approved: what became of it is said too (written to SharePoint, or not, and why).
        app(AuditLogger::class)->log(AuditLogger::EDIT_APPROVED, $request, new: array_filter([
            'Value' => $request->newShown(),
            'Result' => EditRequest::LABELS[$request->status] ?? $request->status,
            'Note' => $request->failure ?: null,
        ], fn ($value) => $value !== null), user: $approver, label: $request->auditLabel());
        $this->tellRequester($request, $approver);

        return $request;
    }

    /**
     * Turns a request down (or, for whoever asked, takes it back). Only a request that has not been written yet.
     *
     * @throws EditException
     */
    public function reject(EditRequest $request, User $decider, string $note): EditRequest
    {
        $note = trim(mb_substr(trim((string) preg_replace('/\s+/u', ' ', $note)), 0, 500));
        $rejected = EditRequest::query()->whereKey($request->id)
            ->whereIn('status', [EditRequest::PENDING, EditRequest::FAILED, EditRequest::CONFLICT])
            ->update([
                'status' => EditRequest::REJECTED,
                'decided_by' => $decider->id,
                'decided_by_name' => mb_substr((string) $decider->name, 0, 255),
                'decided_at' => now(),
                'decision_note' => $note === '' ? null : $note,
                'failure' => null,
                'updated_at' => now(),
            ]);
        if ($rejected !== 1) {
            throw new EditException(__('SharePointDocs::edits.decided'), 409);
        }
        $request->refresh();
        app(AuditLogger::class)->log(AuditLogger::EDIT_REJECTED, $request, new: array_filter([
            'Value' => $request->newShown(),
            'Note' => filled($request->decision_note) ? $request->decision_note : null,
        ], fn ($value) => $value !== null), user: $decider, label: $request->auditLabel());
        $this->tellRequester($request, $decider);

        return $request;
    }

    /**
     * Writes cells of one row straight away (for who mayWriteDirectly()). The workbook is read once; every cell, and the
     * column that tells the row, are checked against what the page showed; the cells are put in, and the file is put back
     * once with the version it was read as (If-Match). Nothing is written when anything is not as it was. Each cell is kept
     * as a request applied by its maker, marked "direct", with a line in the audit trail.
     *
     * @param array<int, string> $values the column (its position in the table) => what it is to hold ('' empties it)
     * @return list<EditRequest> the cells written (a cell that held the new value already is not)
     *
     * @throws EditException refused, or SharePoint refused the file (what is wrong is said in words; nothing was written)
     * @throws GraphException the workbook could not be read for the page
     */
    public function writeRow(User $user, string $workbook, int $sheetIndex, int $line, array $values): array
    {
        if (! self::mayWriteDirectly($user, $workbook)) {
            throw new EditException(__('SharePointDocs::edits.not_direct'), 403);
        }
        $book = $this->workbooks->tables($workbook);
        if ($book['stale']) {
            throw new EditException(__('SharePointDocs::edits.stale'), 503);
        }
        $sheet = $book['sheets'][$sheetIndex] ?? null;
        if ($sheet === null) {
            throw new EditException(__('SharePointDocs::edits.no_sheet'));
        }
        $row = array_search($line, $sheet['lines'], true);
        if ($row === false) {
            throw new EditException(__('SharePointDocs::edits.no_row'));
        }
        $anchor = $this->anchor($workbook, $sheet);

        // What changes, cell by cell (a value that is what the cell holds already is left out).
        $requests = [];
        foreach ($values as $column => $value) {
            $title = (string) ($sheet['columns'][$column] ?? '');
            if ($column < 0 || ! isset($sheet['positions'][$column])) {
                throw new EditException(__('SharePointDocs::edits.column_fixed_named', ['column' => $title]));
            }
            $oldText = (string) ($sheet['rows'][$row][$column] ?? '');
            $oldNumber = isset($sheet['numbers'][$row][$column]) ? (float) $sheet['numbers'][$row][$column] : null;
            try {
                [$kind, $text, $number] = self::interpret($value, $oldText, $oldNumber);
            } catch (EditException $e) {
                throw new EditException(__('SharePointDocs::edits.in_column', ['column' => $title, 'message' => $e->getMessage()]), $e->status);
            }
            if (self::same($kind, $text, $number, $oldText, $oldNumber)) {
                continue;
            }
            if (isset($sheet['formulas'][$row][$column])) {
                throw new EditException(__('SharePointDocs::edits.column_formula', ['column' => $title]));
            }
            $requests[] = new EditRequest([
                'workbook' => $workbook,
                'sheet' => $sheet['name'],
                'cell' => XlsxPatcher::columnLetters($sheet['positions'][$column]) . $line,
                'line' => $line,
                'column_title' => mb_substr($title, 0, 200),
                'anchor_cell' => $anchor === null ? null : XlsxPatcher::columnLetters($sheet['positions'][$anchor]) . $line,
                'anchor_value' => $anchor === null ? null : mb_substr((string) ($sheet['rows'][$row][$anchor] ?? ''), 0, 500),
                'old_text' => mb_substr($oldText, 0, 2000),
                'old_number' => self::stored($oldNumber),
                'new_kind' => $kind,
                'new_value' => $kind === EditRequest::KIND_EMPTY ? null : $text,
                'new_number' => self::stored($number),
            ]);
        }
        if ($requests === []) {
            throw new EditException(__('SharePointDocs::edits.nothing_changed'));
        }

        // A cell that waits for a decision is decided first, not written over by who could decide it.
        $waiting = EditRequest::query()->where('workbook', $workbook)->where('sheet', $sheet['name'])
            ->whereIn('cell', array_map(fn (EditRequest $one) => $one->cell, $requests))
            ->whereIn('status', [EditRequest::PENDING, EditRequest::PROCESSING])->first();
        if ($waiting !== null) {
            throw new EditException(__('SharePointDocs::edits.cell_waiting', ['cell' => $waiting->cell, 'name' => $waiting->requested_by_name]), 409);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(max(60, 3 * (int) config('sharepoint-docs.preview_time_limit', 25)));
        }
        try {
            $source = $this->workbooks->download($workbook);
        } catch (GraphException $e) {
            Log::warning('The workbook could not be read to write a row.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            throw new EditException($e->writeMessage(), 502);
        }

        $write = [];
        foreach ($requests as $request) {
            $check = $this->check($source['bytes'], $request);
            if ($check['verdict'] === 'applied') {
                continue;
            }
            if ($check['verdict'] === EditRequest::CONFLICT) {
                throw new EditException(__('SharePointDocs::edits.row_changed', ['line' => $line, 'cell' => $request->cell]), 409);
            }
            if ($check['verdict'] !== 'write') {
                throw new EditException($check['message'](app()->getLocale()), 422);
            }
            $write[] = $request;
        }
        if ($write === []) {
            $this->workbooks->forget($workbook);

            return [];
        }

        $bytes = $source['bytes'];
        try {
            foreach ($write as $request) {
                $bytes = $this->patcher->write($bytes, $request->sheet, $request->cell, match ($request->new_kind) {
                    EditRequest::KIND_EMPTY => null,
                    EditRequest::KIND_NUMBER => (float) $request->new_number,
                    default => (string) $request->new_value,
                });
            }
        } catch (WorkbookNotSaved $e) {
            Log::warning('The changed workbook could not be saved on this server.', ['workbook' => $workbook, 'detail' => $e->getMessage()]);

            throw new EditException(__('SharePointDocs::edits.row_not_kept'), 503);
        } catch (RuntimeException $e) {
            Log::warning('A row could not be written in the workbook.', ['workbook' => $workbook, 'detail' => $e->getMessage()]);

            throw new EditException(__('SharePointDocs::edits.row_unsupported'), 422);
        }

        try {
            $this->workbooks->replace($workbook, $source, $bytes);
        } catch (GraphException $e) {
            Log::warning('A row could not be written to the workbook.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);
            // Refused as saved by somebody else meanwhile: or by this very request a moment ago, when Microsoft was asked a
            // second time (GraphClient::upload). The file says which.
            if (! ($e->reason === GraphException::CHANGED && $this->hasAll($workbook, $write))) {
                throw new EditException(match ($e->reason) {
                    GraphException::CHANGED => __('SharePointDocs::edits.row_saved_meanwhile'),
                    default => $e->writeMessage(),
                }, match ($e->reason) {
                    GraphException::CHANGED => 412,
                    GraphException::LOCKED => 423,
                    default => $e->httpStatus(),
                });
            }
        }
        $this->workbooks->forget($workbook);

        $now = now();
        foreach ($write as $request) {
            $request->forceFill([
                'status' => EditRequest::APPLIED,
                'direct' => true,
                'requested_by' => $user->id,
                'requested_by_name' => mb_substr((string) $user->name, 0, 255),
                'decided_by' => $user->id,
                'decided_by_name' => mb_substr((string) $user->name, 0, 255),
                'decided_at' => $now,
                'applied_at' => $now,
            ])->save();
            app(AuditLogger::class)->log(AuditLogger::EDIT_DIRECT, $request, ['Value' => $request->oldShown()],
                ['Value' => $request->newShown()] + array_filter(['Column' => $request->column_title]), $user, $request->auditLabel());
        }
        Log::info('Cells of a SharePoint workbook were written directly.', [
            'workbook' => $workbook, 'sheet' => $sheet['name'], 'line' => $line,
            'cells' => array_map(fn (EditRequest $one) => $one->cell, $write), 'by' => $user->id,
        ]);

        return $write;
    }

    /** After a refused save: whether the workbook holds every new value now (it was this request's own save). */
    private function hasAll(string $workbook, array $requests): bool
    {
        try {
            $now = $this->workbooks->download($workbook);
        } catch (GraphException) {
            return false;
        }
        foreach ($requests as $request) {
            if ($this->check($now['bytes'], $request)['verdict'] !== 'applied') {
                return false;
            }
        }

        return true;
    }

    /** Tells whoever may approve (Update on "Persetujuan Edit") that a request waits, each in their language; never the one who made it. */
    private function tellApprovers(EditRequest $made, User $maker): void
    {
        try {
            $approvers = Notifier::whoMay('documents.edit-approvals.approve', 'Update')->reject(fn (User $user) => (int) $user->id === (int) $maker->id);
            app(Notifier::class)->send($approvers, UserNotification::EDIT_REQUESTED,
                fn (string $locale) => __('SharePointDocs::edits.notify.requested_title', [], $locale),
                fn (string $locale) => __('SharePointDocs::edits.notify.requested_body', [
                    'name' => $maker->name,
                    'place' => $made->placeShown(),
                    'old' => self::quoted($made->oldShown($locale)),
                    'new' => self::quoted($made->newShown($locale)),
                ], $locale),
                route('documents.edit-approvals.index', ['status' => EditRequest::PENDING, 'search' => $made->searchTerms()], false));
        } catch (Throwable $e) {
            Log::warning('The approvers of a change could not be told.', ['request' => $made->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Tells whoever asked what became of the request, in their language: applied, rejected, a conflict, or failed (which can
     * be tried again). Nothing when they took it back themselves.
     */
    private function tellRequester(EditRequest $request, User $decider): void
    {
        if ((int) $request->requested_by === (int) $decider->id) {
            return;
        }
        try {
            $maker = User::query()->find($request->requested_by);
            $place = $request->placeShown();
            $failure = fn (string $locale) => __('SharePointDocs::edits.notify.failure_body', ['place' => $place, 'failure' => (string) $request->failureIn($locale)], $locale);
            // The title by its key, the text in the language of whoever asked.
            [$title, $body] = match ($request->status) {
                EditRequest::APPLIED => ['applied_title', fn (string $locale) => __('SharePointDocs::edits.notify.applied_body',
                    ['place' => $place, 'new' => self::quoted($request->newShown($locale)), 'name' => $decider->name], $locale)],
                EditRequest::REJECTED => ['rejected_title', fn (string $locale) => filled($request->decision_note)
                    ? __('SharePointDocs::edits.notify.rejected_body_note', ['place' => $place, 'name' => $decider->name, 'note' => $request->decision_note], $locale)
                    : __('SharePointDocs::edits.notify.rejected_body', ['place' => $place, 'name' => $decider->name], $locale)],
                EditRequest::CONFLICT => ['conflict_title', $failure],
                EditRequest::FAILED => ['failed_title', $failure],
                default => [null, null],
            };
            if ($maker === null || $title === null) {
                return;
            }
            // The page of the requests when it is open to them; otherwise the row of the workbook they asked about.
            $url = match (true) {
                MenuAccess::allows($maker, 'documents.edit-approvals.index', 'Read') => route('documents.edit-approvals.index',
                    ['status' => $request->status, 'search' => $request->searchTerms()], false),
                Route::has("documents.{$request->workbook}.index") => route("documents.{$request->workbook}.index",
                    ['sheet' => $request->sheet, 'line' => $request->line], false),
                default => null,
            };
            app(Notifier::class)->send($maker, UserNotification::EDIT_DECIDED, fn (string $locale) => __("SharePointDocs::edits.notify.{$title}", [], $locale), $body, $url);
        } catch (Throwable $e) {
            Log::warning('Whoever asked for a change could not be told.', ['request' => $request->id, 'error' => $e->getMessage()]);
        }
    }

    /** A value in a notification, cut short: "Rp 44444". */
    private static function quoted(string $value): string
    {
        return '"' . Str::limit($value, 60, '…') . '"';
    }

    /**
     * Words of SharePointDocs::edits in any language: what is stored with a request is Indonesian (Language::DEFAULT), what
     * is said to a person is in theirs.
     *
     * @return Closure(string): string
     */
    private static function words(string $key, array $replace = []): Closure
    {
        return fn (string $locale) => __('SharePointDocs::edits.' . $key, $replace, $locale);
    }

    /**
     * The workbook as it is now, checked against what the request saw, and written.
     *
     * @return array{0: string, 1: (Closure(string): string)|null} the final status, and the words for it when it is not "applied"
     */
    private function write(EditRequest $request): array
    {
        try {
            $source = $this->workbooks->download($request->workbook);
        } catch (GraphException $e) {
            Log::warning('The workbook could not be read to write a change.', ['request' => $request->id, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return [EditRequest::FAILED, fn (string $locale) => $e->writeMessage($locale)];
        }

        $check = $this->check($source['bytes'], $request);
        if ($check['verdict'] === 'applied') {
            $this->workbooks->forget($request->workbook);

            return [EditRequest::APPLIED, self::words('already')];
        }
        if ($check['verdict'] !== 'write') {
            return [$check['verdict'], $check['message']];
        }

        try {
            $bytes = $this->patcher->write(
                $source['bytes'],
                $request->sheet,
                $request->cell,
                match ($request->new_kind) {
                    EditRequest::KIND_EMPTY => null,
                    EditRequest::KIND_NUMBER => (float) $request->new_number,
                    default => (string) $request->new_value,
                }
            );
        } catch (WorkbookNotSaved $e) {
            Log::warning('The changed workbook could not be saved on this server.', ['request' => $request->id, 'detail' => $e->getMessage()]);

            return [EditRequest::FAILED, self::words('not_kept')];
        } catch (RuntimeException $e) {
            Log::warning('A change could not be made in the workbook.', ['request' => $request->id, 'detail' => $e->getMessage()]);

            return [EditRequest::FAILED, self::words('cell_unsupported')];
        }

        try {
            $this->workbooks->replace($request->workbook, $source, $bytes);
        } catch (GraphException $e) {
            Log::warning('The workbook could not be written.', ['request' => $request->id, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            // Refused because the file was saved by somebody else meanwhile: or by this very request, a moment ago, when
            // Microsoft was asked a second time (see GraphClient::upload). The file says which.
            if ($e->reason === GraphException::CHANGED) {
                return $this->afterRefusedSave($request, $e);
            }

            return [EditRequest::FAILED, fn (string $locale) => $e->writeMessage($locale)];
        }

        return [EditRequest::APPLIED, null];
    }

    /** @return array{0: string, 1: (Closure(string): string)|null} */
    private function afterRefusedSave(EditRequest $request, GraphException $refusal): array
    {
        $refused = fn (string $locale) => $refusal->writeMessage($locale);
        try {
            $now = $this->workbooks->download($request->workbook);
        } catch (GraphException) {
            return [EditRequest::FAILED, $refused];
        }
        $check = $this->check($now['bytes'], $request);
        $this->workbooks->forget($request->workbook);

        return match ($check['verdict']) {
            'applied' => [EditRequest::APPLIED, null],
            'write' => [EditRequest::CONFLICT, $refused],
            default => [$check['verdict'], $check['message']],
        };
    }

    /**
     * Whether the cell can be written: 'write' (it is as it was), 'applied' (it already holds the new value), or the
     * status that says why not, with the words (in any language: see words()).
     *
     * @return array{verdict: string, message: (Closure(string): string)|null}
     */
    private function check(string $bytes, EditRequest $request): array
    {
        $conflict = fn (string $why, array $replace = []) => ['verdict' => EditRequest::CONFLICT, 'message' => self::words($why, $replace)];
        $failed = fn (string $why, array $replace = []) => ['verdict' => EditRequest::FAILED, 'message' => self::words($why, $replace)];

        try {
            $cell = $this->patcher->read($bytes, $request->sheet, $request->cell);
            $anchor = $request->anchor_cell === null ? null : $this->patcher->read($bytes, $request->sheet, $request->anchor_cell);
        } catch (SheetNotFound $e) {
            return $conflict('sheet_gone', ['sheet' => $request->sheet]);
        } catch (RuntimeException $e) {
            Log::warning('The cell of a change could not be read.', ['request' => $request->id, 'detail' => $e->getMessage()]);

            return $failed('unreadable');
        }

        if ($cell['formula']) {
            return $conflict('now_formula', ['cell' => $request->cell]);
        }
        if (! $cell['supported']) {
            return $failed('cell_kind', ['cell' => $request->cell]);
        }
        if ($anchor !== null && ! self::matchesAnchor($anchor, (string) $request->anchor_value)) {
            return $conflict('row_moved', ['line' => $request->line]);
        }
        if (self::matchesNew($cell, $request)) {
            return ['verdict' => 'applied', 'message' => null];
        }
        if (! self::matches($cell, (string) $request->old_text, $request->old_number)) {
            return $conflict('cell_changed', ['cell' => $request->cell, 'now' => mb_strimwidth((string) $cell['raw'], 0, 80, '…')]);
        }
        if ($cell['date1904'] && ($request->old_number !== null || $request->new_kind === EditRequest::KIND_NUMBER)) {
            return $failed('date1904');
        }

        return ['verdict' => 'write', 'message' => null];
    }

    /**
     * The column that tells a row: the one the config names for the sheet, else the first.
     *
     * @return int|null its position in the table
     */
    private function anchor(string $workbook, array $sheet): ?int
    {
        if ($sheet['positions'] === []) {
            return null;
        }
        $anchors = (array) config("sharepoint-docs.workbooks.{$workbook}.edit.anchors", []);
        foreach ($anchors as $title => $column) {
            if (mb_strtolower(trim((string) $title)) !== mb_strtolower(trim((string) $sheet['name']))) {
                continue;
            }
            foreach ($sheet['columns'] as $at => $name) {
                if (mb_strtolower(trim((string) $name)) === mb_strtolower(trim((string) $column)) && isset($sheet['positions'][$at])) {
                    return $at;
                }
            }
        }

        return 0;
    }

    /**
     * What the person typed, as what the cell is to hold: text, a number (the cell held one, or was empty and an
     * ordinary number is typed), a date (the cell is shown as one), or nothing.
     *
     * @return array{0: string, 1: string, 2: ?float} the kind, the value as it is kept and shown, the number behind it
     */
    private static function interpret(string $input, string $oldText, ?float $oldNumber): array
    {
        // One line: a line break in a cell of a register is a mistake of whoever typed it, and the table shows one line.
        $text = trim((string) preg_replace('/\s+/u', ' ', $input));
        if (! mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) === 1) {
            throw new EditException(__('SharePointDocs::edits.bad_characters'));
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            throw new EditException(__('SharePointDocs::edits.too_long', ['max' => self::MAX_LENGTH]));
        }
        if ($text === '') {
            return [EditRequest::KIND_EMPTY, '', null];
        }

        if ($oldNumber !== null) {
            if (preg_match('/^(\d{2}-\d{2}-\d{4}( \d{2}:\d{2})?|\d{2}:\d{2})$/', $oldText) === 1) {
                [$serial, $shown] = self::date($text, $oldText);

                return [EditRequest::KIND_NUMBER, $shown, $serial];
            }
            $number = self::number($text);
            if ($number === null) {
                throw new EditException(__('SharePointDocs::edits.number'));
            }

            return [EditRequest::KIND_NUMBER, self::shownNumber($number), $number];
        }

        // An empty cell gets a number when an ordinary number is typed (as Excel does); text stays text, "000123" too.
        if ($oldText === '' && preg_match('/^-?(0|[1-9]\d{0,14})(,\d{1,10})?$/', $text) === 1) {
            $number = (float) str_replace(',', '.', $text);

            return [EditRequest::KIND_NUMBER, self::shownNumber($number), $number];
        }

        return [EditRequest::KIND_TEXT, $text, null];
    }

    /** @return array{0: float, 1: string} the day number Excel has for it, and the date as it is shown */
    private static function date(string $text, string $oldText): array
    {
        $wrong = new EditException(__('SharePointDocs::edits.date'));

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $text, $time) === 1 && preg_match('/^\d{2}:\d{2}$/', $oldText) === 1) {
            if ((int) $time[1] > 23 || (int) $time[2] > 59) {
                throw $wrong;
            }

            return [((int) $time[1] * 60 + (int) $time[2]) / 1440, sprintf('%02d:%02d', $time[1], $time[2])];
        }
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})(?: (\d{1,2}):(\d{2}))?$/', $text, $at) !== 1
            || ! checkdate((int) $at[2], (int) $at[1], (int) $at[3])
            || (int) ($at[4] ?? 0) > 23 || (int) ($at[5] ?? 0) > 59
            || (int) $at[3] < 1900) {
            throw $wrong;
        }
        $moment = (new DateTimeImmutable())->setDate((int) $at[3], (int) $at[2], (int) $at[1])->setTime((int) ($at[4] ?? 0), (int) ($at[5] ?? 0));
        $withTime = isset($at[4]);

        return [(float) Date::dateTimeToExcel($moment), $moment->format($withTime ? 'd-m-Y H:i' : 'd-m-Y')];
    }

    /** A number as it is typed in Indonesia (1.500,50), or plainly (1500.5): null when it is no number. */
    private static function number(string $text): ?float
    {
        $text = str_replace(' ', '', $text);
        $plain = match (true) {
            preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $text) === 1 => str_replace(['.', ','], ['', '.'], $text),
            preg_match('/^-?\d+(,\d+)?$/', $text) === 1 => str_replace(',', '.', $text),
            preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 => $text,
            default => null,
        };
        // Excel keeps 15 digits of a number and drops the rest: what has more is not written, whole, by it.
        if ($plain === null || strlen((string) preg_replace('/\D|^0+(?=\d)/', '', $plain)) > 15) {
            return null;
        }
        $number = (float) $plain;

        return is_finite($number) ? $number : null;
    }

    /** A number as it is kept in the table: the shortest text that is the same number again (a float column loses digits). */
    private static function stored(?float $number): ?string
    {
        if ($number === null) {
            return null;
        }
        $text = (string) json_encode($number);

        return str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;
    }

    private static function shownNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 10, ',', '.'), '0'), ',');
    }

    private static function same(string $kind, string $text, ?float $number, string $oldText, ?float $oldNumber): bool
    {
        return match ($kind) {
            EditRequest::KIND_EMPTY => $oldText === '' && $oldNumber === null,
            EditRequest::KIND_NUMBER => $oldNumber !== null && self::equal($number, $oldNumber),
            default => $oldNumber === null && self::squash($text) === self::squash($oldText),
        };
    }

    /** Whether a cell of the workbook holds what the table showed: $text and $number are what the reader made of it. */
    private static function matches(array $cell, string $text, ?float $number): bool
    {
        $raw = $cell['exists'] ? $cell['raw'] : null;
        if ($raw === null) {
            return $text === '' && $number === null;
        }
        if ($number !== null) {
            return $cell['type'] === 'n' && is_numeric($raw) && self::equal((float) $raw, $number);
        }
        if ($cell['type'] === 'b') {
            $raw = $raw === '1' ? 'TRUE' : 'FALSE';
        }

        return $cell['type'] !== 'n' || ! is_numeric($raw)
            ? self::squash($raw) === self::squash($text)
            : false;
    }

    /** The cell that tells the row: its text as shown, or, when it is a plain number, the number. */
    private static function matchesAnchor(array $cell, string $text): bool
    {
        $raw = $cell['exists'] ? $cell['raw'] : null;
        if ($raw === null) {
            return $text === '';
        }
        if (self::squash($raw) === self::squash($text)) {
            return true;
        }
        $number = self::number($text);

        return is_numeric($raw) && $number !== null && self::equal((float) $raw, $number);
    }

    private static function matchesNew(array $cell, EditRequest $request): bool
    {
        $raw = $cell['exists'] ? $cell['raw'] : null;

        return match ($request->new_kind) {
            EditRequest::KIND_EMPTY => $raw === null,
            EditRequest::KIND_NUMBER => $raw !== null && $cell['type'] === 'n' && is_numeric($raw) && self::equal((float) $raw, (float) $request->new_number),
            default => $raw !== null && ! ($cell['type'] === 'n' && is_numeric($raw)) && $raw === (string) $request->new_value,
        };
    }

    private static function equal(?float $a, ?float $b): bool
    {
        return $a !== null && $b !== null && abs($a - $b) <= 1e-9 * max(1.0, abs($a), abs($b));
    }

    /** Text without its blanks: the table shows a cell with its runs of blanks made one, so that is how two texts are compared. */
    private static function squash(string $text): string
    {
        return (string) preg_replace('/\s+/u', '', $text);
    }
}
