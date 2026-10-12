<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\ScanLinks;
use Modules\SharePointDocs\Services\ScanRegister;
use Modules\SharePointDocs\Services\SharePointFolder;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A SharePoint folder shown as a page. Everything is read with the application's own Microsoft
 * identity: the list, and a file when it is shown inside the page ("Lihat"). So what a role may
 * see here is decided by the folder's menu (Read), not by the reader's rights in SharePoint.
 * The links to SharePoint itself open with the reader's own account.
 */
class FolderController extends Controller
{
    private const SORTS = ['modified', 'name', 'label', 'extension', 'size', 'masterlist'];

    public function __construct(private readonly SharePointFolder $folders, private readonly ScanLinks $scans)
    {
    }

    public function index(Request $request, string $folder)
    {
        $settings = $this->settings($folder);

        return view('SharePointDocs::index', [
            'pageTitle' => $settings['title'],
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route("documents.{$folder}.index", [], false), 'label' => $settings['title'], 'active' => true],
            ],
            'folderKey' => $folder,
            'title' => $settings['title'],
            'subtitle' => $settings['subtitle'],
            'split' => $settings['name_split'],
            'configured' => $this->folders->configured($folder),
            'sharePointUrl' => $this->folders->link($folder),
            'cacheMinutes' => max(1, (int) config('sharepoint-docs.cache_minutes', 10)),
            // The sheet of a workbook that lists these files, when this reader may open it: its columns come with the files.
            'register' => $this->scans->offeredOnFolder($folder, $request->user()),
        ]);
    }

    /**
     * The files and folders of one folder (searched, sorted, one page of them). When a sheet of a workbook lists these files
     * (config "scans") and the reader may open that workbook, every file also says which row of the sheet is its own, and
     * what that row's note says.
     */
    public function list(Request $request, string $folder): JsonResponse
    {
        $settings = $this->settings($folder);
        $started = $this->scans->begin();

        $path = SharePointFolder::cleanPath($request->query('path'));
        if ($path === null) {
            return response()->json(['message' => 'Folder tidak dikenali.'], 422);
        }

        try {
            $listing = $this->folders->listing($folder, $path, $request->boolean('refresh'));
        } catch (GraphException $e) {
            Log::warning('SharePoint folder could not be read.', [
                'folder' => $folder, 'path' => $path, 'reason' => $e->reason, 'detail' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->userMessage(), 'reason' => $e->reason], $e->httpStatus());
        }

        $everything = array_map(fn (array $item) => $item + $this->nameParts($item['name'], $item['folder'], $settings['name_split']), $listing['items']);
        $files = array_filter($everything, fn (array $row) => ! $row['folder']);

        $register = $this->scans->forFolder($folder, $everything, $request->user(), $request->boolean('refresh'), $started, (bool) $listing['stale']);
        foreach ($register['files'] ?? [] as $at => $own) {
            $everything[$at]['register'] = $own + ['open' => $register['open'], 'sheet' => $register['sheet']];
        }

        $rows = $this->search($everything, self::text($request, 'search'));
        // A link from the page of the workbook: the files that have this document number, and nothing else. (The number of a file
        // that has no separator in its name is the whole name, which can be long: the cut is the longest name SharePoint allows.)
        $code = mb_substr(self::text($request, 'code'), 0, ScanRegister::CODE_MOST);
        if (ScanRegister::codeKey($code) !== '') {
            $separator = ScanLinks::separator($folder);
            $rows = array_values(array_filter($rows, fn (array $row) => ! $row['folder']
                && ScanRegister::codeKey(ScanRegister::codeOfFile($row['name'], $separator)) === ScanRegister::codeKey($code)));
        } else {
            $code = '';
        }
        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'modified';
        $descending = match ($request->query('dir')) {
            'asc' => false,
            'desc' => true,
            default => $sort === 'modified', // newest first, as the SharePoint view is opened
        };
        $rows = $this->sort($rows, $sort, $descending);

        $perPage = min(max((int) self::text($request, 'paginated') ?: 25, 1), 100);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max((int) self::text($request, 'page'), 1), $lastPage);
        $offset = ($page - 1) * $perPage;
        $shown = array_slice($rows, $offset, $perPage);

        return response()->json([
            'data' => array_map(fn (array $row) => $this->present($row, $path), $shown),
            'meta' => [
                'current_page' => $page,
                'from' => $shown ? $offset + 1 : null,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $shown ? $offset + count($shown) : null,
                'total' => $total,
            ],
            'folder' => [
                'path' => $path,
                'web_url' => $this->folders->webUrl($folder, $path),
                'fetched_at' => self::time($listing['fetched_at']),
                // Of the whole folder, whatever is searched for.
                'files' => count($files),
                'folders' => count($everything) - count($files),
                'size' => self::size(array_sum(array_column($files, 'size'))),
                'truncated' => (bool) $listing['truncated'],
                'stale' => (bool) $listing['stale'],
            ] + ($code === '' ? [] : ['code' => $code]),
        ] + ($register === null ? [] : ['register' => self::register($register)]));
    }

    /**
     * One file, sent to be shown in the page's viewer (an <iframe>): a PDF or an image as it
     * is, with a content type the application chooses. Asked for by the id the list gave.
     */
    public function file(Request $request, string $folder): Response
    {
        $this->settings($folder);

        $path = SharePointFolder::cleanPath($request->query('path'));
        $id = self::text($request, 'id');
        if ($path === null || $id === '') {
            return $this->refuse($request, 422, 'File tidak dikenali.');
        }

        try {
            $file = $this->folders->file($folder, $path, $id);
        } catch (GraphException $e) {
            Log::warning('SharePoint file could not be shown.', [
                'folder' => $folder, 'path' => $path, 'reason' => $e->reason, 'detail' => $e->getMessage(),
            ]);
            $message = $e->reason === GraphException::NOT_FOUND
                ? 'File tidak ditemukan di SharePoint. Mungkin sudah dipindahkan atau dihapus; muat ulang daftarnya.'
                : $e->userMessage();

            return $this->refuse($request, $e->httpStatus(), $message, $e->reason);
        }

        // The application reads the file for the reader: who looked at what is written down.
        Log::info('SharePoint file shown in the application.', [
            'user' => $request->user()?->id, 'folder' => $folder, 'path' => $path, 'file' => $file['name'],
        ]);

        return response($file['body'], 200, [
            'Content-Type' => $file['type'],
            'Content-Length' => (string) strlen($file['body']),
            'Content-Disposition' => self::inline($file['name']),
            // The type above is the one to go by, whatever the bytes look like.
            'X-Content-Type-Options' => 'nosniff',
            // A scan of a signed form: not left in the browser's cache.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{title: string, subtitle: ?string, name_split: ?array{separator: string, code: string, label: string}} */
    private function settings(string $folder): array
    {
        $settings = config("sharepoint-docs.folders.{$folder}");
        abort_unless(is_array($settings), 404);

        $split = $settings['name_split'] ?? null;
        $separator = is_array($split) ? ($split['separator'] ?? null) : null;

        return [
            'title' => (string) ($settings['title'] ?? $folder),
            'subtitle' => filled($settings['subtitle'] ?? null) ? (string) $settings['subtitle'] : null,
            'name_split' => is_string($separator) && $separator !== '' ? [
                'separator' => $separator,
                'code' => (string) ($split['code'] ?? 'Kode'),
                'label' => (string) ($split['label'] ?? 'Nama'),
            ] : null,
        ];
    }

    /**
     * "ICTCOF123 - Nama Penerima.pdf" => code "ICTCOF123", label "Nama Penerima", extension "pdf".
     * A name without the separator (or a folder) is all label.
     *
     * @return array{code: string, label: string, extension: string}
     */
    private function nameParts(string $name, bool $folder, ?array $split): array
    {
        $extension = $folder ? '' : SharePointFolder::extension($name);
        $base = $extension === '' ? $name : substr($name, 0, -strlen($extension) - 1);

        $at = $split === null || $folder ? false : mb_strpos($base, $split['separator']);
        if ($at === false || $at === 0) {
            return ['code' => '', 'label' => $base, 'extension' => $extension];
        }

        return [
            'code' => trim(mb_substr($base, 0, $at)),
            'label' => trim(mb_substr($base, $at + mb_strlen($split['separator']))),
            'extension' => $extension,
        ];
    }

    /** Every word typed must be somewhere in the name; upper and lower case are the same. */
    private function search(array $rows, string $search): array
    {
        $terms = preg_split('/\s+/u', mb_strtolower(mb_substr($search, 0, 100)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($terms === []) {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($terms) {
            $name = mb_strtolower($row['name']);
            foreach ($terms as $term) {
                if (! str_contains($name, $term)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** Folders first, as in SharePoint; names in natural order ("COF2" before "COF10"). */
    private function sort(array $rows, string $sort, bool $descending): array
    {
        usort($rows, function (array $a, array $b) use ($sort, $descending) {
            if ($a['folder'] !== $b['folder']) {
                return $a['folder'] ? -1 : 1;
            }

            $order = match ($sort) {
                // A folder shows no size: folders stay in name order among themselves.
                'size' => $a['folder'] ? 0 : $a['size'] <=> $b['size'],
                'modified' => ($a['modified'] ?? 0) <=> ($b['modified'] ?? 0),
                'label' => strnatcasecmp($a['label'], $b['label']),
                'extension' => strcmp($a['extension'], $b['extension']),
                // Files without a row first (what is left to do), then those whose name differs, then the rest.
                'masterlist' => self::rank($a) <=> self::rank($b),
                default => strnatcasecmp($a['name'], $b['name']),
            };
            if ($order !== 0) {
                return $descending ? -$order : $order;
            }

            // Equal on the chosen column: always in the same order, by name.
            return strnatcasecmp($a['name'], $b['name']) ?: strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    private function present(array $row, string $path): array
    {
        $url = $row['url'];
        if ($url !== null && ! $row['folder']) {
            // Opens the file in SharePoint's viewer instead of downloading it.
            $url .= (str_contains($url, '?') ? '&' : '?') . 'web=1';
        }

        return [
            'type' => $row['folder'] ? 'folder' : 'file',
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'label' => $row['label'],
            'extension' => $row['extension'],
            'size' => $row['folder'] ? null : self::size($row['size']),
            'modified' => $row['modified'] === null ? null : self::time($row['modified']),
            'modified_by' => $row['modified_by'],
            'url' => $url,
            // null: a name that cannot be asked for by path; the row then only links to SharePoint.
            'path' => $row['folder'] ? SharePointFolder::cleanPath(ltrim($path . '/' . $row['name'], '/')) : null,
            // Whether "Lihat" shows it inside the page: the same rules as SharePointFolder::file().
            'preview' => ! $row['folder'] && $row['id'] !== null
                && SharePointFolder::previewType($row['name']) !== null
                && $row['size'] <= SharePointFolder::previewLimit(),
        ] + (isset($row['register'])
            // What the sheet that lists the files says about this file (only when there is such a sheet and it could be read).
            ? ['masterlist' => self::masterlist($row), 'note' => $row['register']['note']]
            : []);
    }

    /** How a file stands against the sheet that lists the files: 0 no row, 1 a row with another name, 2 its own row, 3 not told. */
    private static function rank(array $row): int
    {
        return match ($row['register']['status'] ?? null) {
            ScanRegister::UNLINKED => 0,
            ScanRegister::DIFFERS => 1,
            ScanRegister::LINKED => 2,
            default => 3,
        };
    }

    /**
     * The "Masterlist" column of a file: whether a row names it, the Excel row, and the way to that row on the page of the
     * workbook (the page opens on the sheet, showing that row of Excel and no other).
     *
     * @return array{status: string, line: ?int, more: int, url: ?string}
     */
    private static function masterlist(array $row): array
    {
        $own = $row['register'];
        $url = $own['lines'] !== []
            ? $own['open'] . '?' . http_build_query(['sheet' => $own['sheet'], 'line' => implode(',', $own['lines'])], '', '&', PHP_QUERY_RFC3986)
            : null;

        return [
            'status' => $own['status'],
            'line' => $own['lines'][0] ?? null,
            // How many more rows name it, or have its number (a file with two rows).
            'more' => max(0, $own['total'] - 1),
            'url' => $url,
        ];
    }

    /**
     * What the page is told about the sheet: where it comes from, how old it is, or why its columns are empty.
     *
     * @return array<string, mixed>
     */
    private static function register(array $register): array
    {
        if (isset($register['unavailable'])) {
            return ['title' => $register['title'], 'unavailable' => $register['unavailable']];
        }

        return [
            'title' => $register['title'],
            'sheet' => $register['sheet'],
            'fetched_at' => self::time($register['fetched_at']),
            'stale' => $register['stale'],
        ];
    }

    /**
     * Why a file is not shown. The viewer is an <iframe>, so a browser gets a small page it can
     * display there; a script asking for JSON gets JSON.
     */
    private function refuse(Request $request, int $status, string $message, ?string $reason = null): Response
    {
        if ($request->expectsJson()) {
            return response()->json(array_filter(['message' => $message, 'reason' => $reason]), $status);
        }

        return response()->view('SharePointDocs::file-unavailable', ['message' => $message], $status);
    }

    /** "inline; filename=..." for any file name: an ASCII stand-in plus the real name (RFC 6266). */
    private static function inline(string $name): string
    {
        $name = str_replace(['/', '\\'], '-', $name);
        $ascii = trim((string) preg_replace('/[^\x20-\x7E]|%/u', '_', $name)) ?: 'file';

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, $ascii);
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

    private static function size(int|float $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes . ' B',
            $bytes < 1024 ** 2 => number_format($bytes / 1024, 0, ',', '.') . ' KB',
            $bytes < 1024 ** 3 => number_format($bytes / 1024 ** 2, 1, ',', '.') . ' MB',
            default => number_format($bytes / 1024 ** 3, 2, ',', '.') . ' GB',
        };
    }
}
