<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\SharePointWorkbook;

/**
 * An Excel workbook in SharePoint shown as a page: one tab per sheet, each a table that can be
 * searched, sorted and paged. Read with the application's own Microsoft identity, so what a
 * role may see here is decided by the workbook's menu (Read), not by the reader's rights in
 * SharePoint. Nothing is written back to the workbook.
 */
class WorkbookController extends Controller
{
    public function __construct(private readonly SharePointWorkbook $workbooks)
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

    /** The rows of one sheet (searched, sorted, one page of them), and for every sheet how many rows match. */
    public function rows(Request $request, string $workbook): JsonResponse
    {
        $this->settings($workbook);

        try {
            $book = $this->workbooks->tables($workbook, $request->boolean('refresh'));
        } catch (GraphException $e) {
            Log::warning('SharePoint workbook could not be read.', ['workbook' => $workbook, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return response()->json(['message' => self::message($e), 'reason' => $e->reason], $e->httpStatus());
        }

        $terms = preg_split('/\s+/u', mb_strtolower(mb_substr(self::text($request, 'search'), 0, 100)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matching = array_map(fn (array $sheet) => $this->matching($sheet, $terms), $book['sheets']);

        $index = min(max((int) self::text($request, 'sheet'), 0), max(0, count($book['sheets']) - 1));
        $sheet = $book['sheets'][$index] ?? ['name' => '', 'truncated' => false, 'columns' => [], 'rows' => [], 'numbers' => [], 'lines' => []];
        $found = $matching[$index] ?? [];

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
            ],
            'data' => array_map(fn (int $at) => ['line' => $sheet['lines'][$at], 'cells' => $sheet['rows'][$at]], $shown),
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
     * @return array<int, int>
     */
    private function matching(array $sheet, array $terms): array
    {
        if ($terms === []) {
            return array_keys($sheet['rows']);
        }

        $found = [];
        foreach ($sheet['rows'] as $at => $cells) {
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
        return match ($e->reason) {
            GraphException::NOT_CONFIGURED => 'Alamat workbook atau akun aplikasi Microsoft belum diisi di pengaturan (.env).',
            GraphException::NOT_FOUND => 'Workbook tidak ditemukan di SharePoint. Mungkin sudah dipindahkan, diganti namanya atau dihapus, atau alamat di pengaturan keliru.',
            GraphException::FORBIDDEN => 'Aplikasi tidak punya izin membaca workbook ini di SharePoint (perlu izin aplikasi Sites.Read.All dengan admin consent).',
            GraphException::UNSUPPORTED => 'Alamat di pengaturan bukan workbook Excel (.xlsx) yang bisa dibaca aplikasi (file yang diberi password tidak bisa dibaca).',
            GraphException::TOO_LARGE => 'Workbook ini terlalu besar untuk ditampilkan di sini.',
            GraphException::TOO_SLOW => 'Workbook terlalu lama diambil dari SharePoint atau dibaca (koneksi lambat atau workbook sangat besar). Coba lagi beberapa saat lagi.',
            default => $e->userMessage(),
        };
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
