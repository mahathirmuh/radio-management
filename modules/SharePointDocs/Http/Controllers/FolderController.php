<?php

namespace Modules\SharePointDocs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\SharePointDocs\Services\ConfigWords;
use Modules\SharePointDocs\Services\FolderView;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\ScanLinks;
use Modules\SharePointDocs\Services\SharePointFolder;
use Symfony\Component\HttpFoundation\Response;

/**
 * A SharePoint folder shown as a page. Everything is read with the application's own Microsoft
 * identity: the list, and a file when it is shown inside the page ("Lihat"). So what a role may
 * see here is decided by the folder's menu (Read), not by the reader's rights in SharePoint.
 * The links to SharePoint itself open with the reader's own account. (The list itself is FolderView's,
 * which the API shows too.)
 */
class FolderController extends Controller
{
    public function __construct(
        private readonly SharePointFolder $folders,
        private readonly ScanLinks $scans,
        private readonly FolderView $view,
    ) {
    }

    public function index(Request $request, string $folder)
    {
        $settings = $this->settings($folder);
        $split = $settings['name_split'];

        return view('SharePointDocs::index', [
            'pageTitle' => $settings['title'],
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route("documents.{$folder}.index", [], false), 'label' => $settings['title'], 'active' => true],
            ],
            'folderKey' => $folder,
            'title' => $settings['title'],
            'split' => $split === null ? null : [
                'code' => ConfigWords::of("scans.folders.{$folder}.code", $split['code']),
                'label' => ConfigWords::of("scans.folders.{$folder}.label", $split['label']),
            ] + $split,
            'configured' => $this->folders->configured($folder),
            'sharePointUrl' => $this->folders->link($folder),
            // The sheet of a workbook that lists these files, when this reader may open it: its columns come with the files.
            'register' => $this->scans->offeredOnFolder($folder, $request->user()),
        ]);
    }

    /**
     * The files and folders of one folder (searched, sorted, one page of them). When a sheet of a workbook lists these files
     * (config "scans") and the reader may open that workbook, every file also says which row of the sheet is its own, and
     * what that row's note says; how many files have their own row and how many do not, and "status" keeps one of the two.
     */
    public function list(Request $request, string $folder): JsonResponse
    {
        $this->settings($folder);

        $path = SharePointFolder::cleanPath($request->query('path'));
        if ($path === null) {
            return response()->json(['message' => __('SharePointDocs::scans.folder_unknown')], 422);
        }

        try {
            $shown = $this->view->page($folder, $path, $request->user(), [
                'refresh' => $request->boolean('refresh'),
                'search' => self::text($request, 'search'),
                'code' => self::text($request, 'code'),
                'status' => self::text($request, 'status'),
                'sort' => $request->query('sort'),
                'descending' => match ($request->query('dir')) {
                    'asc' => false,
                    'desc' => true,
                    default => null,
                },
                'per_page' => (int) self::text($request, 'paginated'),
                'page' => (int) self::text($request, 'page'),
            ]);
        } catch (GraphException $e) {
            Log::warning('SharePoint folder could not be read.', [
                'folder' => $folder, 'path' => $path, 'reason' => $e->reason, 'detail' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->userMessage(), 'reason' => $e->reason], $e->httpStatus());
        }

        $count = count($shown['rows']);

        return response()->json([
            'data' => array_map(fn (array $row) => $this->present($row, $path), $shown['rows']),
            'meta' => [
                'current_page' => $shown['page'],
                'from' => $count ? $shown['offset'] + 1 : null,
                'last_page' => $shown['last_page'],
                'per_page' => $shown['per_page'],
                'to' => $count ? $shown['offset'] + $count : null,
                'total' => $shown['total'],
            ],
            'folder' => [
                'path' => $path,
                'web_url' => $this->folders->webUrl($folder, $path),
                'fetched_at' => self::time($shown['fetched_at']),
                'synced' => self::synced($shown['fetched_at']),
                // Of the whole folder, whatever is searched for.
                'files' => $shown['files'],
                'folders' => $shown['folders'],
                'size' => self::size($shown['size']),
                'truncated' => $shown['truncated'],
                'stale' => $shown['stale'],
            ] + ($shown['code'] === '' ? [] : ['code' => $shown['code']])
                + ($shown['status'] === '' ? [] : ['status' => $shown['status']]),
        ] + ($shown['register'] === null ? [] : ['register' => self::register($shown['register']) + ($shown['counts'] ?? [])]));
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
            return $this->refuse($request, 422, __('SharePointDocs::scans.file_unknown'));
        }

        try {
            $file = $this->folders->file($folder, $path, $id);
        } catch (GraphException $e) {
            Log::warning('SharePoint file could not be shown.', [
                'folder' => $folder, 'path' => $path, 'reason' => $e->reason, 'detail' => $e->getMessage(),
            ]);
            $message = $e->reason === GraphException::NOT_FOUND ? __('SharePointDocs::scans.file_gone') : $e->userMessage();

            return $this->refuse($request, $e->httpStatus(), $message, $e->reason);
        }

        // The application reads the file for the reader: who looked at what is written down.
        Log::info('SharePoint file shown in the application.', [
            'user' => $request->user()?->id, 'folder' => $folder, 'path' => $path, 'file' => $file['name'],
        ]);

        return response($file['body'], 200, [
            'Content-Type' => $file['type'],
            'Content-Length' => (string) strlen($file['body']),
            'Content-Disposition' => FolderView::disposition($file['name']),
            // The type above is the one to go by, whatever the bytes look like.
            'X-Content-Type-Options' => 'nosniff',
            // A scan of a signed form: not left in the browser's cache.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{title: string, subtitle: ?string, name_split: ?array{separator: string, code: string, label: string}} */
    private function settings(string $folder): array
    {
        $settings = FolderView::settings($folder);
        abort_unless($settings !== null, 404);

        return $settings;
    }

    private function present(array $row, string $path): array
    {
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
            // Opens the file in SharePoint's viewer instead of downloading it.
            'url' => FolderView::webUrl($row),
            // null: a name that cannot be asked for by path; the row then only links to SharePoint.
            'path' => FolderView::subPath($path, $row),
            // Whether "Lihat" shows it inside the page: the same rules as SharePointFolder::file().
            'preview' => FolderView::previewable($row),
        ] + (isset($row['register'])
            // What the sheet that lists the files says about this file (only when there is such a sheet and it could be read).
            ? ['masterlist' => self::masterlist($row), 'note' => $row['register']['note']]
            : []);
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

    private static function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function time(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp, config('app.timezone'))->format('d-m-Y H:i');
    }

    /**
     * When the list was read, as the page's "Sinkron terakhir" says it: the hour, and the day in words or as a date.
     *
     * @return array{time: string, day: string}
     */
    private static function synced(int $timestamp): array
    {
        $at = Carbon::createFromTimestamp($timestamp, config('app.timezone'));
        $today = Carbon::now(config('app.timezone'));

        return [
            'time' => $at->format('H:i'),
            'day' => match (true) {
                $at->isSameDay($today) => __('SharePointDocs::common.today'),
                $at->isSameDay($today->copy()->subDay()) => __('SharePointDocs::common.yesterday'),
                default => $at->format('d-m-Y'),
            },
        ];
    }

    /** A size as the language of the page writes numbers: 1,5 MB in Indonesian, 1.5 MB in English. */
    private static function size(int|float $bytes): string
    {
        return match (true) {
            $bytes < 1024 => $bytes . ' B',
            $bytes < 1024 ** 2 => Language::number($bytes / 1024) . ' KB',
            $bytes < 1024 ** 3 => Language::number($bytes / 1024 ** 2, 1) . ' MB',
            default => Language::number($bytes / 1024 ** 3, 2) . ' GB',
        };
    }
}
