<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\SharePointDocs\Models\EditRequest;
use Modules\SharePointDocs\Services\CellEdits;
use Modules\SharePointDocs\Services\EditException;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\MenuAccess;
use Modules\SharePointDocs\Services\ScanLinks;
use Modules\SharePointDocs\Services\ScanRegister;
use Modules\SharePointDocs\Services\SharePointWorkbook;
use Modules\SharePointDocs\Services\XlsxPatcher;

/**
 * An Excel workbook in SharePoint shown as a page: one tab per sheet, each a table that can be
 * searched, sorted and paged. Read with the application's own Microsoft identity, so what a
 * role may see here is decided by the workbook's menu (Read), not by the reader's rights in
 * SharePoint. Nothing is written back to the workbook from here: a role with Update on the menu can ask
 * for a change of a cell (edit()), which is written only after somebody else has approved it
 * (EditApprovalController).
 */
class WorkbookController extends Controller
{
    public function __construct(private readonly SharePointWorkbook $workbooks, private readonly ScanLinks $scans)
    {
    }

    public function index(string $workbook)
    {
        $settings = $this->settings($workbook);

        return view('SharePointDocs::workbook', [
            'pageTitle' => $settings['title'],
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route("documents.{$workbook}.index", [], false), 'label' => $settings['title'], 'active' => true],
            ],
            'workbookKey' => $workbook,
            'title' => $settings['title'],
            'subtitle' => $settings['subtitle'],
            'configured' => $this->workbooks->configured($workbook),
            'cacheMinutes' => max(1, (int) config('sharepoint-docs.cache_minutes', 10)),
        ]);
    }

    /**
     * The rows of one sheet (searched, sorted, one page of them), and for every sheet how many rows match.
     * A sheet that is tied to a folder of scans (config "scans") has a last column that says whether the file
     * of each row is in the folder, and the counts of that for the cards on the page; "status" shows only the
     * rows of one kind.
     */
    public function rows(Request $request, string $workbook): JsonResponse
    {
        $this->settings($workbook);
        $started = $this->scans->begin();

        try {
            $book = $this->workbooks->tables($workbook, $request->boolean('refresh'));
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return response()->json(['message' => self::message($e), 'reason' => $e->reason], $e->httpStatus());
        }

        // The sheet on screen: the one that may be checked against a folder (the others are as they are).
        $index = $this->sheetIndex($book['sheets'], self::text($request, 'sheet'), self::text($request, 'tab'));
        $scans = $this->scans->addToBook($workbook, $book, $request->user(), $request->boolean('refresh'), $started, $index);

        // The words typed are looked for in every column but the one that says whether the file is there: the cards are for that.
        $terms = preg_split('/\s+/u', mb_strtolower(mb_substr(self::text($request, 'search'), 0, 100)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matching = [];
        foreach ($book['sheets'] as $at => $one) {
            $matching[$at] = $this->matching($one, $terms, $scans[$at]['column'] ?? null);
        }

        $sheet = $book['sheets'][$index] ?? ['name' => '', 'truncated' => false, 'columns' => [], 'rows' => [], 'numbers' => [], 'lines' => [], 'positions' => [], 'formulas' => []];
        $found = $matching[$index] ?? [];

        // The card that was clicked: only the rows of that kind (what is searched for still counts).
        $scan = $scans[$index] ?? null;
        $status = self::text($request, 'status');
        if (isset($scan['statuses']) && array_key_exists($status, ScanRegister::TEXT)) {
            $found = array_values(array_filter($found, fn (int $at) => $scan['statuses'][$at] === $status));
        } else {
            $status = '';
        }

        // A link from the page of the folder: the row (or rows) of Excel that it is about, and nothing else.
        $lines = self::lines($request);
        if ($lines !== []) {
            $found = array_values(array_filter($found, fn (int $at) => in_array($sheet['lines'][$at] ?? null, $lines, true)));
        }

        // "row" is the order of the workbook, the last row first: a register grows at the bottom.
        $sort = self::text($request, 'sort');
        $column = preg_match('/^c(\d{1,3})$/', $sort, $match) === 1 && (int) $match[1] < count($sheet['columns']) ? (int) $match[1] : null;
        $descending = match ($request->query('dir')) {
            'asc' => false,
            'desc' => true,
            default => $column === null,
        };
        $found = $this->sort($sheet, $found, $column, $descending);

        $perPage = min(max((int) self::text($request, 'paginated') ?: 25, 1), 100);
        $total = count($found);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max((int) self::text($request, 'page'), 1), $lastPage);
        $offset = ($page - 1) * $perPage;
        $shown = array_slice($found, $offset, $perPage);

        // Whether the reader may ask for a change of a cell (Update on the menu), and which cells of these rows already have a request waiting.
        $edit = CellEdits::enabled($workbook) && MenuAccess::allows($request->user(), "documents.{$workbook}.edit", 'Update');
        $waiting = $edit ? $this->waiting($workbook, $sheet, array_map(fn (int $at) => $sheet['lines'][$at], $shown)) : [];

        return response()->json([
            'sheets' => array_map(fn (array $one, int $at) => [
                'index' => $at,
                'name' => $one['name'],
                'rows' => count($one['rows']),
                // null: nothing is searched for.
                'matches' => $terms === [] ? null : count($matching[$at]),
            ], $book['sheets'], array_keys($book['sheets'])),
            'sheet' => [
                'index' => $index,
                'name' => $sheet['name'],
                'columns' => $sheet['columns'],
                'sort' => $column === null ? 'row' : "c{$column}",
                'dir' => $descending ? 'desc' : 'asc',
                'truncated' => (bool) $sheet['truncated'],
                // How many columns are real columns of the sheet (the last one may be the check against the folder, which is no cell).
                'editable_columns' => $edit ? count($sheet['positions'] ?? []) : 0,
            ] + ($lines === [] ? [] : ['lines' => $lines]) + ($scan === null ? [] : ['scan' => self::scan($scan, $status)]),
            'data' => array_map(fn (int $at) => ['line' => $sheet['lines'][$at], 'cells' => $sheet['rows'][$at]]
                + (isset($scan['statuses']) ? ['scan' => ['status' => $scan['statuses'][$at], 'code' => $scan['codes'][$at]]] : [])
                // For who may ask for a change: the cells that hold a formula (not changed from here), and those that wait for a decision.
                + ($edit && isset($sheet['formulas'][$at]) ? ['locked' => array_keys($sheet['formulas'][$at])] : [])
                + ($edit && isset($waiting[$sheet['lines'][$at]]) ? ['waiting' => $waiting[$sheet['lines'][$at]]] : []), $shown),
            'meta' => [
                'current_page' => $page,
                'from' => $shown ? $offset + 1 : null,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $shown ? $offset + count($shown) : null,
                'total' => $total,
            ],
            'workbook' => [
                'name' => $book['name'],
                'modified' => $book['modified'] === null ? null : self::time($book['modified']),
                'fetched_at' => self::time($book['fetched_at']),
                'web_url' => $book['web_url'],
                'stale' => (bool) $book['stale'],
            ],
        ]);
    }

    /**
     * Asks for a change of one cell. Nothing is written to the workbook: the request waits for somebody else to approve it
     * (the page "Persetujuan Edit"). Only for a role that has Update on this page's menu: the route is PATCH.
     */
    public function edit(Request $request, string $workbook, CellEdits $edits): JsonResponse
    {
        $this->settings($workbook);
        $data = $request->validate([
            'sheet' => ['required', 'integer', 'min:0', 'max:1000'],
            'line' => ['required', 'integer', 'min:1', 'max:2000000'],
            'column' => ['required', 'integer', 'min:0', 'max:500'],
            'value' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $made = $edits->submit($request->user(), $workbook, (int) $data['sheet'], (int) $data['line'], (int) $data['column'], (string) ($data['value'] ?? ''));
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read to ask for a change.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return response()->json(['message' => self::message($e), 'reason' => $e->reason], $e->httpStatus());
        }
        app(AuditLogger::class)->log(AuditLogger::EDIT_REQUESTED, $made, ['Value' => $made->oldShown()],
            ['Value' => $made->newShown()] + array_filter(['Column' => $made->column_title]), label: EditApprovalController::auditLabel($made));

        return response()->json([
            'message' => "Pengajuan perubahan sel {$made->cell} dikirim. Isi workbook baru berubah setelah disetujui.",
            'id' => $made->id,
            'cell' => $made->cell,
            'status' => $made->status,
        ], 201);
    }

    /**
     * The cells of the rows on show that a request is waiting for, by the number of the row in Excel: column in the table => the new value.
     *
     * @param array<int, int> $lines
     * @return array<int, array<int, string>>
     */
    private function waiting(string $workbook, array $sheet, array $lines): array
    {
        if ($lines === [] || $sheet['positions'] === []) {
            return [];
        }

        $found = [];
        $requests = EditRequest::query()->where('workbook', $workbook)->where('sheet', $sheet['name'])
            ->whereIn('line', $lines)->whereIn('status', [EditRequest::PENDING, EditRequest::PROCESSING])->get();
        foreach ($requests as $one) {
            $column = array_search(XlsxPatcher::columnNumber((string) preg_replace('/\d+$/', '', $one->cell)), $sheet['positions'], true);
            if ($column !== false) {
                $found[$one->line][$column] = $one->newShown();
            }
        }

        return $found;
    }

    /**
     * The position of the sheet that was asked for. The tabs of the page ask by position ("tab=1", from 0): that is never a
     * name, however a sheet is called ("1"). A link from another page says "sheet=COF": by the name (a sheet that is called "2025"
     * is found that way too), else by the number it is; the first sheet when it is neither, the last when the number is too large.
     */
    private function sheetIndex(array $sheets, string $requested, string $tab = ''): int
    {
        if (preg_match('/^\d{1,4}$/', $tab) === 1) {
            return min((int) $tab, max(0, count($sheets) - 1));
        }

        if ($requested !== '') {
            foreach ($sheets as $at => $sheet) {
                if (mb_strtolower(trim((string) $sheet['name'])) === mb_strtolower($requested)) {
                    return (int) $at;
                }
            }
        }

        if (is_numeric($requested)) {
            return min(max((int) $requested, 0), max(0, count($sheets) - 1));
        }

        return 0;
    }

    /**
     * The Excel rows that a link from the page of the folder asks for ("line=2,9"): whole numbers from 1, no more than
     * ScanRegister::LINES_MOST of them; anything else is left out.
     *
     * @return array<int, int>
     */
    private static function lines(Request $request): array
    {
        $found = [];
        foreach (preg_split('/[,\s]+/', self::text($request, 'line'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match('/^\d{1,9}$/', $part) === 1 && (int) $part > 0) {
                $found[(int) $part] = true;
            }
            if (count($found) >= ScanRegister::LINES_MOST) {
                break;
            }
        }

        return array_keys($found);
    }

    /**
     * What the page is told about the check of a sheet against its folder: the counts for the cards, which kind of
     * row is shown, how old the folder's listing is, and where its page is; or why there is no check.
     *
     * @param array<string, mixed> $scan
     * @return array<string, mixed>
     */
    private static function scan(array $scan, string $status): array
    {
        if (isset($scan['unavailable'])) {
            return ['unavailable' => $scan['unavailable']];
        }

        return [
            'column' => $scan['column'],
            'counts' => $scan['counts'],
            'status' => $status === '' ? null : $status,
            'fetched_at' => self::time($scan['fetched_at']),
            'stale' => $scan['stale'],
            'folder' => $scan['folder'],
            'open' => $scan['open'],
        ];
    }

    /** @return array{title: string, subtitle: ?string} */
    private function settings(string $workbook): array
    {
        $settings = config("sharepoint-docs.workbooks.{$workbook}");
        abort_unless(is_array($settings), 404);

        return [
            'title' => (string) ($settings['title'] ?? $workbook),
            'subtitle' => filled($settings['subtitle'] ?? null) ? (string) $settings['subtitle'] : null,
        ];
    }

    /**
     * The positions of the rows in which every word typed occurs somewhere (in any column, upper
     * and lower case being the same); all rows when nothing is typed.
     *
     * @param int|null $skip a column that is not looked in (the one that says whether a file is there: "ada" would find every row)
     * @return array<int, int>
     */
    private function matching(array $sheet, array $terms, ?int $skip = null): array
    {
        if ($terms === []) {
            return array_keys($sheet['rows']);
        }

        $found = [];
        foreach ($sheet['rows'] as $at => $cells) {
            if ($skip !== null) {
                unset($cells[$skip]);
            }
            $text = mb_strtolower(implode("\n", $cells));
            foreach ($terms as $term) {
                if (! str_contains($text, $term)) {
                    continue 2;
                }
            }
            $found[] = $at;
        }

        return $found;
    }

    /**
     * By a column: numbers and dates by their value, text in natural order ("COF2" before
     * "COF10"), numbers before text, and empty cells last whichever way it goes. Without a
     * column: the order of the workbook.
     *
     * @param array<int, int> $found positions of rows
     * @return array<int, int>
     */
    private function sort(array $sheet, array $found, ?int $column, bool $descending): array
    {
        usort($found, function (int $a, int $b) use ($sheet, $column, $descending) {
            if ($column === null) {
                return $descending ? $b <=> $a : $a <=> $b;
            }

            [$textA, $textB] = [$sheet['rows'][$a][$column] ?? '', $sheet['rows'][$b][$column] ?? ''];
            if (($textA === '') !== ($textB === '')) {
                return $textA === '' ? 1 : -1;
            }
            [$numberA, $numberB] = [$sheet['numbers'][$a][$column] ?? null, $sheet['numbers'][$b][$column] ?? null];
            $order = match (true) {
                $numberA !== null && $numberB !== null => $numberA <=> $numberB,
                $numberA !== null => -1,
                $numberB !== null => 1,
                default => strnatcasecmp($textA, $textB),
            };

            // Equal in that column: in the order of the workbook.
            return $order === 0 ? $a <=> $b : ($descending ? -$order : $order);
        });

        return $found;
    }

    /** What the page says when the workbook cannot be shown (what Microsoft said goes to the log only). */
    private static function message(GraphException $e): string
    {
        return $e->workbookMessage();
    }

    private static function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function time(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp, config('app.timezone'))->format('d-m-Y H:i');
    }
}
