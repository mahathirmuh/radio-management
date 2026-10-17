<?php

namespace Modules\SharePointDocs\Services;

use App\Models\UserManagement\User;
use Modules\SharePointDocs\Models\EditRequest;

/**
 * An Excel workbook in SharePoint as tables: one sheet at a time, its rows searched, sorted and one page of them, and for
 * the sheet that is tied to a folder of scans (config "scans") whether the file of each row is in the folder. The page of
 * the workbook and the API both show it from here, each in its own words.
 *
 * Read with the application's own Microsoft identity: what may be seen is decided by the workbook's menu, not by the
 * reader's rights in SharePoint.
 */
final class WorkbookView
{
    public function __construct(private readonly SharePointWorkbook $workbooks, private readonly ScanLinks $scans)
    {
    }

    /**
     * The settings of a workbook of config('sharepoint-docs.workbooks'), or null when there is no workbook by that key.
     *
     * @return array{title: string, subtitle: ?string}|null
     */
    public static function settings(string $workbook): ?array
    {
        $settings = config("sharepoint-docs.workbooks.{$workbook}");
        if (! is_array($settings)) {
            return null;
        }

        return [
            'title' => (string) ($settings['title'] ?? $workbook),
            'subtitle' => filled($settings['subtitle'] ?? null) ? (string) $settings['subtitle'] : null,
        ];
    }

    /**
     * One page of the rows of one sheet, and for every sheet how many rows match what is searched for.
     *
     * @param array{refresh?: bool, sheet?: string, tab?: string, search?: string, status?: string, lines?: array<int, int>,
     *     column?: ?int, descending?: ?bool, per_page?: int, page?: int, edit?: bool} $options
     *     sheet: by its name, else by the number it is; tab: by its position (wins). column: the column to sort by, null for
     *     the order of the workbook. edit: whether the reader may ask for a change (it is told which cells hold a formula and
     *     which wait for a decision).
     * @return array{
     *     book: array, index: int, sheet: array, sheets: array<int, array{index: int, name: string, rows: int, matches: ?int}>,
     *     rows: array<int, array>, total: int, page: int, per_page: int, last_page: int, offset: int,
     *     column: ?int, descending: bool, status: string, lines: array<int, int>, scan: ?array, edit: bool
     * }
     *
     * @throws GraphException the workbook could not be read
     */
    public function page(string $workbook, ?User $user, array $options = []): array
    {
        $started = $this->scans->begin();
        $refresh = (bool) ($options['refresh'] ?? false);

        $book = $this->workbooks->tables($workbook, $refresh);

        // The sheet on screen: the one that may be checked against a folder (the others are as they are).
        $index = self::sheetIndex($book['sheets'], (string) ($options['sheet'] ?? ''), (string) ($options['tab'] ?? ''));
        $scans = $this->scans->addToBook($workbook, $book, $user, $refresh, $started, $index);

        // The words typed are looked for in every column but the one that says whether the file is there: the cards are for that.
        $terms = preg_split('/\s+/u', mb_strtolower(mb_substr((string) ($options['search'] ?? ''), 0, 100)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matching = [];
        foreach ($book['sheets'] as $at => $one) {
            $matching[$at] = self::matching($one, $terms, $scans[$at]['column'] ?? null);
        }

        $sheet = $book['sheets'][$index] ?? ['name' => '', 'truncated' => false, 'columns' => [], 'rows' => [], 'numbers' => [], 'lines' => [], 'positions' => [], 'formulas' => []];
        $found = $matching[$index] ?? [];

        // The card that was clicked: only the rows of that kind (what is searched for still counts).
        $scan = $scans[$index] ?? null;
        $status = (string) ($options['status'] ?? '');
        if (isset($scan['statuses']) && array_key_exists($status, ScanRegister::TEXT)) {
            $found = array_values(array_filter($found, fn (int $at) => $scan['statuses'][$at] === $status));
        } else {
            $status = '';
        }

        // A link from the page of the folder: the row (or rows) of Excel that it is about, and nothing else.
        $lines = array_values($options['lines'] ?? []);
        if ($lines !== []) {
            $found = array_values(array_filter($found, fn (int $at) => in_array($sheet['lines'][$at] ?? null, $lines, true)));
        }

        // "row" is the order of the workbook, the last row first: a register grows at the bottom.
        $column = $options['column'] ?? null;
        $column = is_int($column) && $column >= 0 && $column < count($sheet['columns']) ? $column : null;
        $descending = $options['descending'] ?? ($column === null);
        $found = self::sort($sheet, $found, $column, $descending);

        $perPage = min(max((int) ($options['per_page'] ?? 25) ?: 25, 1), 100);
        $total = count($found);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max((int) ($options['page'] ?? 1), 1), $lastPage);
        $offset = ($page - 1) * $perPage;
        $shown = array_slice($found, $offset, $perPage);

        // Which cells of these rows already have a request waiting (only for who may ask for a change).
        $edit = (bool) ($options['edit'] ?? false);
        $waiting = $edit ? self::waiting($workbook, $sheet, array_map(fn (int $at) => $sheet['lines'][$at], $shown)) : [];

        return [
            'book' => $book,
            'index' => $index,
            'sheet' => $sheet,
            'sheets' => array_map(fn (array $one, int $at) => [
                'index' => $at,
                'name' => $one['name'],
                'rows' => count($one['rows']),
                // null: nothing is searched for.
                'matches' => $terms === [] ? null : count($matching[$at]),
            ], $book['sheets'], array_keys($book['sheets'])),
            'rows' => array_map(fn (int $at) => ['line' => $sheet['lines'][$at], 'cells' => $sheet['rows'][$at]]
                + (isset($scan['statuses']) ? ['scan' => ['status' => $scan['statuses'][$at], 'code' => $scan['codes'][$at]]] : [])
                // For who may ask for a change: the cells that hold a formula (not changed from here), and those that wait for a decision.
                + ($edit && isset($sheet['formulas'][$at]) ? ['locked' => array_keys($sheet['formulas'][$at])] : [])
                + ($edit && isset($waiting[$sheet['lines'][$at]]) ? ['waiting' => $waiting[$sheet['lines'][$at]]] : []), $shown),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'offset' => $offset,
            'column' => $column,
            'descending' => $descending,
            'status' => $status,
            'lines' => $lines,
            'scan' => $scan,
            'edit' => $edit,
        ];
    }

    /**
     * The Excel rows that a link from the page of the folder asks for ("2,9"): whole numbers from 1, no more than
     * ScanRegister::LINES_MOST of them; anything else is left out.
     *
     * @return array<int, int>
     */
    public static function lines(string $text): array
    {
        $found = [];
        foreach (preg_split('/[,\s]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
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
     * The position of the sheet that was asked for. The tabs of the page ask by position ("tab=1", from 0): that is never a
     * name, however a sheet is called ("1"). A link from another page says "sheet=COF": by the name (a sheet that is called "2025"
     * is found that way too), else by the number it is; the first sheet when it is neither, the last when the number is too large.
     */
    public static function sheetIndex(array $sheets, string $requested, string $tab = ''): int
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
     * The cells of the rows on show that a request is waiting for, by the number of the row in Excel: column in the table => the new value.
     *
     * @param array<int, int> $lines
     * @return array<int, array<int, string>>
     */
    private static function waiting(string $workbook, array $sheet, array $lines): array
    {
        if ($lines === [] || ($sheet['positions'] ?? []) === []) {
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
     * The positions of the rows in which every word typed occurs somewhere (in any column, upper
     * and lower case being the same); all rows when nothing is typed.
     *
     * @param int|null $skip a column that is not looked in (the one that says whether a file is there: "ada" would find every row)
     * @return array<int, int>
     */
    private static function matching(array $sheet, array $terms, ?int $skip = null): array
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
    private static function sort(array $sheet, array $found, ?int $column, bool $descending): array
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
}
