<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Api\Http\Resources\EditRequestResource;
use Modules\SharePointDocs\Services\CellEdits;
use Modules\SharePointDocs\Services\EditException;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\MenuAccess;
use Modules\SharePointDocs\Services\ScanLinks;
use Modules\SharePointDocs\Services\ScanRegister;
use Modules\SharePointDocs\Services\SharePointWorkbook;
use Modules\SharePointDocs\Services\WorkbookView;

/**
 * An Excel workbook of SharePoint as its page shows it ("ICT Masterlist"): its sheets as tables, and on the sheet that is
 * tied to a folder of scans whether the file of each row is in the folder. Nothing is written to the workbook from here:
 * a change of a cell is asked for, and written only after somebody else has approved it ("Persetujuan Edit"). Read with
 * the application's own Microsoft identity. 30 requests a minute per token.
 *
 * @tags SharePoint documents
 */
class DocumentWorkbookController extends ApiController
{
    public function __construct(private readonly WorkbookView $view, private readonly SharePointWorkbook $workbooks)
    {
    }

    /**
     * The workbook and its sheets
     *
     * Every sheet with its columns and how many rows it has. `editable`: whether this token may ask for a change of a cell
     * (Update on the page's menu, a token that writes). What SharePoint holds is kept for some minutes; `refresh=1` asks it
     * again (when what is kept is more than 15 seconds old).
     */
    public function show(Request $request, string $workbook): JsonResponse
    {
        $settings = $this->settings($workbook);
        $request->validate(['refresh' => ['nullable', 'boolean']]);

        try {
            $book = $this->workbooks->tables($workbook, $request->boolean('refresh'));
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read for the API.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return $this->sharePointError($e, workbook: true);
        }
        $tied = array_map(fn ($title) => mb_strtolower(trim((string) $title)), array_keys(ScanLinks::ofWorkbook($workbook)));

        return response()->json(['data' => [
            'title' => $settings['title'],
            'name' => $book['name'],
            'modified_at' => self::iso($book['modified']),
            'fetched_at' => self::iso($book['fetched_at']),
            /** Opens the workbook in Excel for the web, for whoever has access there. */
            'web_url' => $book['web_url'],
            /** SharePoint could not be reached: this is what was kept from before. */
            'stale' => (bool) $book['stale'],
            'editable' => $this->mayAsk($request, $workbook),
            'sheets' => array_map(fn (array $sheet, int $at) => [
                /** The position to ask for its rows by (`sheet`). */
                'index' => $at,
                'name' => $sheet['name'],
                'columns' => $sheet['columns'],
                'rows' => count($sheet['rows']),
                /** Too large to read whole: the rows are not all there. */
                'truncated' => (bool) $sheet['truncated'],
                /** Its rows are checked against a folder of scans (the rows get a last column "File scan"). */
                'scan_check' => in_array(mb_strtolower(trim((string) $sheet['name'])), $tied, true),
            ], $book['sheets'], array_keys($book['sheets'])),
        ]]);
    }

    /**
     * The rows of a sheet
     *
     * The table of the page: the cells of each row as shown there (dates as dd-mm-yyyy, numbers with a decimal comma), with
     * `line`, the row in Excel. The order of the workbook, last row first, unless sorted by a column. On the sheet that is
     * checked against a folder of scans the last column is "File scan", every row has `scan` (`ok`, `missing`, `differs` or
     * `below`) and `sheet.scan` counts them; `status` shows only the rows of one kind. For a token that may ask for a change,
     * `locked` names the columns that hold a formula and `waiting` the cells that wait for a decision (column => new value).
     */
    public function rows(Request $request, string $workbook): JsonResponse
    {
        $this->settings($workbook);
        $request->validate([
            /** The sheet: its name, else its position (from 0). The first sheet when not given. */
            'sheet' => ['nullable', 'string', 'max:100'],
            /** Words that must all be somewhere in the row (not in "File scan"). */
            'search' => ['nullable', 'string', 'max:100'],
            /** Only the rows whose file is: ok, missing, differs or below (the sheet that is checked against a folder). */
            'status' => ['nullable', Rule::in(array_keys(ScanRegister::TEXT))],
            /** Only these rows of Excel, comma separated (at most 20). */
            'line' => ['nullable', 'string', 'max:300'],
            /** "row" (the order of the workbook), or the position of a column (from 0). */
            'sort' => ['nullable', 'string', 'regex:/^(row|\d{1,3})$/'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $sort = (string) $request->input('sort', 'row');
        try {
            $shown = $this->view->page($workbook, $request->user(), [
                'refresh' => $request->boolean('refresh'),
                'sheet' => trim((string) $request->input('sheet', '')),
                'search' => (string) $request->input('search', ''),
                'status' => (string) $request->input('status', ''),
                'lines' => WorkbookView::lines((string) $request->input('line', '')),
                'column' => ctype_digit($sort) ? (int) $sort : null,
                'descending' => match ($request->input('direction')) {
                    'asc' => false,
                    'desc' => true,
                    default => null,
                },
                'per_page' => $this->perPage($request),
                'page' => $request->integer('page', 1),
                'edit' => $this->mayAsk($request, $workbook),
            ]);
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read for the API.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return $this->sharePointError($e, workbook: true);
        }

        $sheet = $shown['sheet'];
        $book = $shown['book'];
        $scan = $shown['scan'];
        $count = count($shown['rows']);

        return response()->json([
            'data' => array_map(fn (array $row) => [
                /** The row in Excel. */
                'line' => $row['line'],
                'cells' => $row['cells'],
            ] + (isset($row['scan']) ? ['scan' => [
                'status' => $row['scan']['status'],
                'label' => ScanRegister::TEXT[$row['scan']['status']] ?? $row['scan']['status'],
                /** The document number of the file to go to ("ICTCOF123"), as the folder can be asked for it (`code`). */
                'file_code' => $row['scan']['code'] === '' ? null : $row['scan']['code'],
            ]] : []) + (isset($row['locked']) ? ['locked' => $row['locked']] : [])
              + (isset($row['waiting']) ? ['waiting' => (object) $row['waiting']] : []), $shown['rows']),
            'meta' => [
                'current_page' => $shown['page'],
                'from' => $count ? $shown['offset'] + 1 : null,
                'last_page' => $shown['last_page'],
                'per_page' => $shown['per_page'],
                'to' => $count ? $shown['offset'] + $count : null,
                'total' => $shown['total'],
            ],
            'sheet' => [
                'index' => $shown['index'],
                'name' => $sheet['name'],
                'columns' => $sheet['columns'],
                /** null: the order of the workbook. */
                'sort_column' => $shown['column'],
                'direction' => $shown['descending'] ? 'desc' : 'asc',
                'truncated' => (bool) $sheet['truncated'],
                /** How many of the columns are cells of the sheet that a change can be asked for (0 when this token may not ask). */
                'editable_columns' => $shown['edit'] ? count($sheet['positions'] ?? []) : 0,
                'lines' => $shown['lines'] === [] ? null : $shown['lines'],
                'scan' => $scan === null ? null : (isset($scan['unavailable']) ? ['unavailable' => $scan['unavailable']] : [
                    /** The position of the column "File scan". */
                    'column' => $scan['column'],
                    /** Rows of each kind: total, ok, missing, differs, below. */
                    'counts' => $scan['counts'],
                    'status' => $shown['status'] === '' ? null : $shown['status'],
                    'folder' => $scan['folder'],
                    'fetched_at' => self::iso($scan['fetched_at']),
                    'stale' => (bool) $scan['stale'],
                ]),
            ],
            /** Every sheet, with how many of its rows match what is searched for (null when nothing is). */
            'sheets' => $shown['sheets'],
            'workbook' => [
                'name' => $book['name'],
                'modified_at' => self::iso($book['modified']),
                'fetched_at' => self::iso($book['fetched_at']),
                'web_url' => $book['web_url'],
                'stale' => (bool) $book['stale'],
            ],
        ]);
    }

    /**
     * Ask for a change of a cell
     *
     * The cell by the sheet's position (`sheet.index` of the rows), the row in Excel (`line`) and the column's position in the
     * table; `value` as it is typed on the page (a number as 1500 or 1.500,50, a date as dd-mm-yyyy; empty to empty the cell).
     * Nothing is written to the workbook now: the request waits until somebody else approves it (Update on "Persetujuan
     * Edit"). Refused when the value is the one the cell holds, the cell holds a formula, or a request for it is waiting (409).
     */
    public function requestEdit(Request $request, string $workbook, CellEdits $edits): JsonResponse
    {
        $this->settings($workbook);
        $data = $request->validate([
            /** The position of the sheet (from 0). */
            'sheet' => ['required', 'integer', 'min:0', 'max:1000'],
            /** The row in Excel. */
            'line' => ['required', 'integer', 'min:1', 'max:2000000'],
            /** The position of the column in the table (from 0). */
            'column' => ['required', 'integer', 'min:0', 'max:500'],
            'value' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $made = $edits->submit($request->user(), $workbook, (int) $data['sheet'], (int) $data['line'], (int) $data['column'], (string) ($data['value'] ?? ''));
        } catch (EditException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read to ask for a change through the API.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return $this->sharePointError($e, workbook: true);
        }

        return response()->json([
            'message' => __('SharePointDocs::workbook.asked', ['cell' => $made->cell]),
            'data' => (new EditRequestResource($made))->resolve($request),
        ], 201);
    }

    /** Whether this token may ask for a change of a cell of the workbook. */
    private function mayAsk(Request $request, string $workbook): bool
    {
        return CellEdits::enabled($workbook)
            && $this->tokenWrites($request)
            && MenuAccess::allows($request->user(), "documents.{$workbook}.edit", 'Update');
    }

    /** @return array{title: string, subtitle: ?string} */
    private function settings(string $workbook): array
    {
        $settings = WorkbookView::settings($workbook);
        abort_if($settings === null, 404);

        return $settings;
    }
}
