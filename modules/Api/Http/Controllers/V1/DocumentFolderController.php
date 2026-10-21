<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\SharePointDocs\Services\FolderView;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\SharePointFolder;
use Symfony\Component\HttpFoundation\Response;

/**
 * A folder of SharePoint as its page shows it ("COF Scan"): its files, and for each file what the sheet of the workbook
 * that lists them says of it. Read with the application's own Microsoft identity: what may be seen is decided by the
 * folder's menu, not by the reader's rights in SharePoint. 30 requests a minute per token.
 *
 * @tags SharePoint documents
 */
class DocumentFolderController extends ApiController
{
    public function __construct(private readonly FolderView $view, private readonly SharePointFolder $folders)
    {
    }

    /**
     * List the files of the folder
     *
     * Folders first, then files; newest first unless sorted otherwise. A folder below is listed with its `path`. When the
     * sheet of the workbook that lists these files may be read by this token's account, every file has `masterlist`: whether
     * a row names it (`linked`), a row has its number but another name (`differs`), or no row has anything to do with it
     * (`unlinked`), with the Excel rows and the note of the row. What SharePoint holds is kept for some minutes; `refresh=1`
     * asks it again (when what is kept is more than 15 seconds old).
     */
    public function index(Request $request, string $folder): JsonResponse
    {
        $settings = $this->settings($folder);
        $request->validate([
            /** A folder below, as the list gives it in `path`. */
            'path' => ['nullable', 'string', 'max:1000'],
            /** Words that must all be in the name. */
            'search' => ['nullable', 'string', 'max:100'],
            /** A document number ("ICT/COF/123" or "ICTCOF123"): only the files that have it. */
            'code' => ['nullable', 'string', 'max:400'],
            'sort' => ['nullable', Rule::in(FolderView::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'refresh' => ['nullable', 'boolean'],
        ]);
        $path = SharePointFolder::cleanPath($request->query('path'));
        if ($path === null) {
            return response()->json(['message' => __('SharePointDocs::scans.folder_unknown'), 'errors' => ['path' => [__('SharePointDocs::scans.folder_unknown')]]], 422);
        }

        try {
            $shown = $this->view->page($folder, $path, $request->user(), [
                'refresh' => $request->boolean('refresh'),
                'search' => (string) $request->input('search', ''),
                'code' => (string) $request->input('code', ''),
                'sort' => $request->input('sort'),
                'descending' => match ($request->input('direction')) {
                    'asc' => false,
                    'desc' => true,
                    default => null,
                },
                'per_page' => $this->perPage($request),
                'page' => $request->integer('page', 1),
            ]);
        } catch (GraphException $e) {
            Log::warning('SharePoint folder could not be read for the API.', ['folder' => $folder, 'path' => $path, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return $this->sharePointError($e);
        }

        $count = count($shown['rows']);
        $register = $shown['register'];

        return response()->json([
            'data' => array_map(fn (array $row) => [
                /** "file" or "folder". */
                'type' => $row['folder'] ? 'folder' : 'file',
                /** The id to download a file by (`/files/{id}`). */
                'id' => $row['id'],
                'name' => $row['name'],
                /** The name split at the folder's separator: "ICTCOF123 - Nama.pdf" is code "ICTCOF123", label "Nama". */
                'code' => $row['code'],
                'label' => $row['label'],
                'extension' => $row['extension'],
                /** Bytes; null for a folder. */
                'size' => $row['folder'] ? null : (int) $row['size'],
                'modified_at' => self::iso($row['modified']),
                'modified_by' => $row['modified_by'],
                /** Opens it in SharePoint, for whoever has access there. */
                'web_url' => FolderView::webUrl($row),
                /** For a folder: the `path` to list it by. */
                'path' => FolderView::subPath($path, $row),
                /** Whether the API gives the file out: a PDF or an image, not larger than the application shows. */
                'downloadable' => FolderView::previewable($row),
            ] + (isset($row['register']) ? ['masterlist' => [
                /** "linked", "differs" or "unlinked". */
                'status' => $row['register']['status'],
                /** The Excel rows that name the file or have its number (the first 20). */
                'lines' => $row['register']['lines'],
                /** How many rows there are. */
                'total' => $row['register']['total'],
                /** The note of the row. */
                'note' => $row['register']['note'],
            ]] : []), $shown['rows']),
            'meta' => [
                'current_page' => $shown['page'],
                'from' => $count ? $shown['offset'] + 1 : null,
                'last_page' => $shown['last_page'],
                'per_page' => $shown['per_page'],
                'to' => $count ? $shown['offset'] + $count : null,
                'total' => $shown['total'],
            ],
            'folder' => [
                'title' => $settings['title'],
                'path' => $path,
                'web_url' => $this->folders->webUrl($folder, $path),
                'fetched_at' => self::iso($shown['fetched_at']),
                /** Of the whole folder, whatever is searched for. */
                'files' => $shown['files'],
                'folders' => $shown['folders'],
                /** Bytes. */
                'size' => (int) $shown['size'],
                /** Too many items to read them all: the list is not complete. */
                'truncated' => $shown['truncated'],
                /** SharePoint could not be reached: this is what was kept from before. */
                'stale' => $shown['stale'],
                'code' => $shown['code'] === '' ? null : $shown['code'],
            ],
            /** The sheet that lists the files: where it comes from and how old it is, or why it could not be set against them. Null when there is none for this token. */
            'masterlist' => $register === null ? null : (isset($register['unavailable'])
                ? ['workbook' => $register['title'], 'sheet' => $register['sheet'], 'unavailable' => $register['unavailable']]
                : ['workbook' => $register['title'], 'sheet' => $register['sheet'], 'fetched_at' => self::iso($register['fetched_at']), 'stale' => $register['stale']]),
        ]);
    }

    /**
     * Download a file of the folder
     *
     * The file as it is in SharePoint: a PDF or an image, up to the size the application shows (10 MB unless set otherwise).
     * Only a file that the list of its folder shows (`id`, and `path` for a folder below). Who fetched which file is written to
     * the log of the application.
     *
     * @response \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function file(Request $request, string $id): Response
    {
        // (The folder is a default of the route: Laravel hands the parameters of a route to the method by their order, the
        // id of the address first, so it is taken by its name.)
        $folder = (string) $request->route('folder');
        $this->settings($folder);
        $request->validate([
            /** The folder below the file is in, as the list gives it. */
            'path' => ['nullable', 'string', 'max:1000'],
        ]);
        $path = SharePointFolder::cleanPath($request->query('path'));
        if ($path === null || trim($id) === '') {
            return response()->json(['message' => __('SharePointDocs::scans.file_unknown')], 422);
        }

        try {
            $file = $this->folders->file($folder, $path, $id);
        } catch (GraphException $e) {
            Log::warning('SharePoint file could not be given out through the API.', ['folder' => $folder, 'path' => $path, 'reason' => $e->reason, 'detail' => $e->getMessage()]);

            return $e->reason === GraphException::NOT_FOUND
                ? response()->json(['message' => __('SharePointDocs::scans.file_gone'), 'reason' => $e->reason], 404)
                : $this->sharePointError($e);
        }

        // The application reads the file for the account of the token: who fetched what is written down.
        $user = $request->user();
        Log::info('SharePoint file given out through the API.', [
            'user' => $user?->id, 'token' => $user?->currentAccessToken()?->getKey(), 'folder' => $folder, 'path' => $path, 'file' => $file['name'],
        ]);

        return response($file['body'], 200, [
            'Content-Type' => $file['type'],
            'Content-Length' => (string) strlen($file['body']),
            'Content-Disposition' => FolderView::disposition($file['name'], attachment: true),
            'X-Content-Type-Options' => 'nosniff',
            // A scan of a signed form: not kept on the way.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array{title: string, subtitle: ?string, name_split: ?array} */
    private function settings(string $folder): array
    {
        $settings = FolderView::settings($folder);
        abort_if($settings === null, 404);

        return $settings;
    }
}
