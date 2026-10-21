<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\SharePointDocs\Services\CellEdits;
use Modules\SharePointDocs\Services\ConfigWords;
use Modules\SharePointDocs\Services\EditException;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\MenuAccess;
use Modules\SharePointDocs\Services\SharePointWorkbook;
use Modules\SharePointDocs\Services\WorkbookView;

/**
 * An Excel workbook in SharePoint shown as a page: one tab per sheet, each a table that can be
 * searched, sorted and paged. Read with the application's own Microsoft identity, so what a
 * role may see here is decided by the workbook's menu (Read), not by the reader's rights in
 * SharePoint. Nothing is written back to the workbook from here: a role with Update on the menu can ask
 * for a change of a cell (edit()), which is written only after somebody else has approved it
 * (EditApprovalController). (The tables themselves are WorkbookView's, which the API shows too.)
 */
class WorkbookController extends Controller
{
    public function __construct(private readonly SharePointWorkbook $workbooks, private readonly WorkbookView $view)
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
            'subtitle' => ConfigWords::of("workbook.workbooks.{$workbook}.subtitle", $settings['subtitle']),
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

        $sort = self::text($request, 'sort');
        try {
            $shown = $this->view->page($workbook, $request->user(), [
                'refresh' => $request->boolean('refresh'),
                'sheet' => self::text($request, 'sheet'),
                'tab' => self::text($request, 'tab'),
                'search' => self::text($request, 'search'),
                'status' => self::text($request, 'status'),
                'lines' => WorkbookView::lines(self::text($request, 'line')),
                'column' => preg_match('/^c(\d{1,3})$/', $sort, $match) === 1 ? (int) $match[1] : null,
                'descending' => match ($request->query('dir')) {
                    'asc' => false,
                    'desc' => true,
                    default => null,
                },
                'per_page' => (int) self::text($request, 'paginated'),
                'page' => (int) self::text($request, 'page'),
                // Whether the reader may ask for a change of a cell (Update on the menu).
                'edit' => CellEdits::enabled($workbook) && MenuAccess::allows($request->user(), "documents.{$workbook}.edit", 'Update'),
                // What a cell that waits for a decision is to hold, as this page says it.
                'locale' => app()->getLocale(),
            ]);
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return response()->json(['message' => self::message($e), 'reason' => $e->reason], $e->httpStatus());
        }

        $sheet = $shown['sheet'];
        $book = $shown['book'];
        $count = count($shown['rows']);

        return response()->json([
            // Whether the reader may write the cells of a row straight away (K13h): the page offers "Edit baris" then.
            'direct' => CellEdits::mayWriteDirectly($request->user(), $workbook),
            'sheets' => $shown['sheets'],
            'sheet' => [
                'index' => $shown['index'],
                'name' => $sheet['name'],
                'columns' => $sheet['columns'],
                'sort' => $shown['column'] === null ? 'row' : "c{$shown['column']}",
                'dir' => $shown['descending'] ? 'desc' : 'asc',
                'truncated' => (bool) $sheet['truncated'],
                // How many columns are real columns of the sheet (the last one may be the check against the folder, which is no cell).
                'editable_columns' => $shown['edit'] ? count($sheet['positions'] ?? []) : 0,
            ] + ($shown['lines'] === [] ? [] : ['lines' => $shown['lines']]) + ($shown['scan'] === null ? [] : ['scan' => self::scan($shown['scan'], $shown['status'])]),
            'data' => $shown['rows'],
            'meta' => [
                'current_page' => $shown['page'],
                'from' => $count ? $shown['offset'] + 1 : null,
                'last_page' => $shown['last_page'],
                'per_page' => $shown['per_page'],
                'to' => $count ? $shown['offset'] + $count : null,
                'total' => $shown['total'],
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

        return response()->json([
            'message' => __('SharePointDocs::workbook.asked', ['cell' => $made->cell]),
            'id' => $made->id,
            'cell' => $made->cell,
            'status' => $made->status,
        ], 201);
    }

    /**
     * Writes cells of one row straight away (K13h): only for a person who may approve changes of the workbook as well as
     * ask for them (CellEdits::mayWriteDirectly). The workbook is read once, checked, and put back once; nothing is written
     * when a cell or the row has changed in SharePoint since the page was loaded.
     */
    public function writeRow(Request $request, string $workbook, CellEdits $edits): JsonResponse
    {
        $this->settings($workbook);
        $data = $request->validate([
            'sheet' => ['required', 'integer', 'min:0', 'max:1000'],
            'line' => ['required', 'integer', 'min:1', 'max:2000000'],
            'values' => ['required', 'array', 'min:1', 'max:500'],
            'values.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $values = [];
        foreach ($data['values'] as $column => $value) {
            if (! is_int($column) && ! ctype_digit((string) $column)) {
                return response()->json(['message' => __('SharePointDocs::workbook.column_unknown')], 422);
            }
            $values[(int) $column] = $value === null ? '' : (string) $value;
        }

        try {
            $made = $edits->writeRow($request->user(), $workbook, (int) $data['sheet'], (int) $data['line'], $values);
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read to write a row.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return response()->json(['message' => self::message($e), 'reason' => $e->reason], $e->httpStatus());
        }

        $line = (int) $data['line'];

        return response()->json([
            'message' => $made === [] ? __('SharePointDocs::workbook.row_same', ['line' => $line])
                : trans_choice('SharePointDocs::workbook.row_saved', count($made), ['line' => $line, 'count' => count($made)]),
            'cells' => array_map(fn ($one) => $one->cell, $made),
        ]);
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
        $settings = WorkbookView::settings($workbook);
        abort_unless($settings !== null, 404);

        return $settings;
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
