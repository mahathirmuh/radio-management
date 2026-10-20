<?php

namespace Modules\SharePointDocs\Services;

use App\Models\UserManagement\User;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * A SharePoint folder as a list: its files and folders, searched, sorted and one page of them, and (when a sheet of a
 * workbook lists these files, see ScanLinks) what that sheet says about each file. The page of the folder and the API
 * both show it from here, each in its own words: the page with sizes and times for people, the API with bytes and ISO
 * times.
 *
 * Everything is read with the application's own Microsoft identity: what may be seen is decided by the folder's menu,
 * not by the reader's rights in SharePoint.
 */
final class FolderView
{
    public const SORTS = ['modified', 'name', 'label', 'extension', 'size', 'masterlist'];

    /** How a file can stand against the sheet that lists the files, to keep only those files: its own row, or no row or a row with another name. */
    public const STATUSES = ['linked', 'unmatched'];

    public function __construct(private readonly SharePointFolder $folders, private readonly ScanLinks $scans)
    {
    }

    /**
     * The settings of a folder of config('sharepoint-docs.folders'), or null when there is no folder by that key.
     *
     * @return array{title: string, subtitle: ?string, name_split: ?array{separator: string, code: string, label: string}}|null
     */
    public static function settings(string $folder): ?array
    {
        $settings = config("sharepoint-docs.folders.{$folder}");
        if (! is_array($settings)) {
            return null;
        }

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
     * One page of what is in the folder (or in a folder below it: $path as SharePointFolder::cleanPath() made it). Every row is
     * an item of the listing (id, name, folder, size, modified, modified_by, url) with its name split (code, label, extension)
     * and, when the sheet that lists the files could be set against them, `register`: what that sheet says of it.
     *
     * @param array{refresh?: bool, search?: string, code?: string, status?: string, sort?: string, descending?: ?bool, per_page?: int, page?: int} $options
     * @return array{
     *     rows: array<int, array>, total: int, page: int, per_page: int, last_page: int, offset: int, sort: string, descending: bool,
     *     code: string, status: string, fetched_at: int, truncated: bool, stale: bool, files: int, folders: int, size: int|float,
     *     register: ?array, counts: ?array{linked: int, unmatched: int}
     * } register: null when nothing is offered beside the files (see ScanLinks::forFolder()); counts and status: only when the sheet
     *   could be set against the files
     *
     * @throws GraphException the folder could not be read
     */
    public function page(string $folder, string $path, ?User $user, array $options = []): array
    {
        $started = $this->scans->begin();
        $settings = self::settings($folder);
        $refresh = (bool) ($options['refresh'] ?? false);

        $listing = $this->folders->listing($folder, $path, $refresh);

        $everything = array_map(fn (array $item) => $item + self::nameParts($item['name'], $item['folder'], $settings['name_split'] ?? null), $listing['items']);
        $files = array_filter($everything, fn (array $row) => ! $row['folder']);

        $register = $this->scans->forFolder($folder, $everything, $user, $refresh, $started, (bool) $listing['stale']);
        foreach ($register['files'] ?? [] as $at => $own) {
            $everything[$at]['register'] = $own + ['open' => $register['open'], 'sheet' => $register['sheet']];
        }

        $rows = self::search($everything, (string) ($options['search'] ?? ''));
        // A link from the page of the workbook: the files that have this document number, and nothing else. (The number of a file
        // that has no separator in its name is the whole name, which can be long: the cut is the longest name SharePoint allows.)
        $code = mb_substr((string) ($options['code'] ?? ''), 0, ScanRegister::CODE_MOST);
        if (ScanRegister::codeKey($code) !== '') {
            $separator = ScanLinks::separator($folder);
            $rows = array_values(array_filter($rows, fn (array $row) => ! $row['folder']
                && ScanRegister::codeKey(ScanRegister::codeOfFile($row['name'], $separator)) === ScanRegister::codeKey($code)));
        } else {
            $code = '';
        }
        // How the files stand against the sheet, counted over the whole folder whatever is searched for; a status asked for keeps the
        // files that stand so (a folder stands nowhere). Neither without a sheet that could be set against the files.
        $counts = isset($register['files']) ? self::counts($everything) : null;
        $status = $counts !== null && in_array($options['status'] ?? null, self::STATUSES, true) ? $options['status'] : '';
        if ($status !== '') {
            $rows = array_values(array_filter($rows, fn (array $row) => self::stands($row) === $status));
        }

        $sort = in_array($options['sort'] ?? null, self::SORTS, true) ? $options['sort'] : 'modified';
        // Newest first, as the SharePoint view is opened.
        $descending = $options['descending'] ?? ($sort === 'modified');
        $rows = self::sort($rows, $sort, $descending);

        $perPage = min(max((int) ($options['per_page'] ?? 25) ?: 25, 1), 100);
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max((int) ($options['page'] ?? 1), 1), $lastPage);
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($rows, $offset, $perPage),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'offset' => $offset,
            'sort' => $sort,
            'descending' => $descending,
            'code' => $code,
            'status' => $status,
            'fetched_at' => (int) $listing['fetched_at'],
            'truncated' => (bool) $listing['truncated'],
            'stale' => (bool) $listing['stale'],
            // Of the whole folder, whatever is searched for.
            'files' => count($files),
            'folders' => count($everything) - count($files),
            'size' => array_sum(array_column($files, 'size')),
            'register' => $register,
            'counts' => $counts,
        ];
    }

    /** The path to list a folder of the list by (null for a file, or a name that cannot be asked for by path). */
    public static function subPath(string $path, array $row): ?string
    {
        return $row['folder'] ? SharePointFolder::cleanPath(ltrim($path . '/' . $row['name'], '/')) : null;
    }

    /** Whether the file can be shown inside the application (and given out by the API): the same rules as SharePointFolder::file(). */
    public static function previewable(array $row): bool
    {
        return ! $row['folder'] && $row['id'] !== null
            && SharePointFolder::previewType($row['name']) !== null
            && $row['size'] <= SharePointFolder::previewLimit();
    }

    /** The address that opens the item in SharePoint: a file in SharePoint's viewer instead of downloading it. */
    public static function webUrl(array $row): ?string
    {
        $url = $row['url'];
        if ($url !== null && ! $row['folder']) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'web=1';
        }

        return $url;
    }

    /** "inline; filename=..." (or "attachment; ...") for any file name: an ASCII stand-in plus the real name (RFC 6266). */
    public static function disposition(string $name, bool $attachment = false): string
    {
        $name = str_replace(['/', '\\'], '-', $name);
        $ascii = trim((string) preg_replace('/[^\x20-\x7E]|%/u', '_', $name)) ?: 'file';

        return HeaderUtils::makeDisposition($attachment ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE, $name, $ascii);
    }

    /**
     * "ICTCOF123 - Nama Penerima.pdf" => code "ICTCOF123", label "Nama Penerima", extension "pdf".
     * A name without the separator (or a folder) is all label.
     *
     * @return array{code: string, label: string, extension: string}
     */
    private static function nameParts(string $name, bool $folder, ?array $split): array
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
    private static function search(array $rows, string $search): array
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
    private static function sort(array $rows, string $sort, bool $descending): array
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

    /** The status of STATUSES a file stands in against the sheet that lists the files; null for a folder, or a file it says nothing of. */
    private static function stands(array $row): ?string
    {
        return match ($row['register']['status'] ?? null) {
            ScanRegister::LINKED => 'linked',
            ScanRegister::DIFFERS, ScanRegister::UNLINKED => 'unmatched',
            default => null,
        };
    }

    /**
     * How many files of the folder stand in each status.
     *
     * @return array{linked: int, unmatched: int}
     */
    private static function counts(array $rows): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $stands = self::stands($row);
            if ($stands !== null) {
                $counts[$stands]++;
            }
        }

        return $counts;
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
}
