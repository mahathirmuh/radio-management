<?php

namespace Modules\SharePointDocs\Services;

/**
 * A sheet whose rows each point to a scan (a link to a file in a SharePoint folder), set against the
 * files that the folder holds.
 *
 * Nothing here asks Microsoft: it works on what has been read already (the sheet as WorkbookReader
 * made it, the files as SharePointFolder lists them), so the page of the workbook and the page of the
 * folder say the same thing about the same pair.
 *
 * A row is
 *  - OK: the file its link names is in the folder. Names are compared letter case aside, as SharePoint
 *    does; a space more or less makes another name, because the link would not find the file either
 *    (WorkbookReader leaves the blanks of a web address as they were typed for that).
 *  - BELOW: its link points into a folder below the folder (the name that was typed in has a "/" in it).
 *  - DIFFERS: no file has the name its link says (the link is empty or not a link to a file, it names
 *    another file, or it spells the name another way), but the folder has a file with the row's document number.
 *  - MISSING: nothing with that name or that number.
 * A file is LINKED (a row names it), DIFFERS (no row names it, but a row has its document number: it is the
 * same pair as a row that DIFFERS or is BELOW, or a second file with a number a row already has a file for) or
 * UNLINKED (no row has anything to do with it).
 */
final class ScanRegister
{
    public const OK = 'ok';

    public const MISSING = 'missing';

    public const DIFFERS = 'differs';

    public const BELOW = 'below';

    public const LINKED = 'linked';

    public const UNLINKED = 'unlinked';

    /** What the extra column of the sheet says for each status of a row. */
    public const TEXT = [
        self::OK => 'Ada',
        self::MISSING => 'Belum ada',
        self::DIFFERS => 'Nama beda',
        self::BELOW => 'Subfolder',
    ];

    /** The longest note that is passed on to the page of the folder, in characters (a longer one is cut and ends in "…"). */
    public const NOTE_MOST = 500;

    /** The most Excel rows that are told of one file (the others are counted). */
    public const LINES_MOST = 20;

    /**
     * The longest document number that a link from the page of the workbook can ask the page of the folder for, in characters (the
     * number of a file with no separator in its name is the whole name: SharePoint allows 400 characters for that, the page of the
     * folder cuts what it is asked for at the same length).
     */
    public const CODE_MOST = 400;

    /**
     * Where a link points: the name of the file ("ICTCOF1 - Nama.pdf") and the folder it is in
     * ("/sites/Team/Shared Documents/Scans", without a slash at its end), both as the address says it (percent
     * codes undone). The whole of what follows the host is the path: the address of a sheet that builds its
     * links by formula does not encode "?" or "#", and a name that has one is still that name. A link copied from the
     * viewer of SharePoint ends in a query or a fragment that is no part of the name ("?web=1", "#page=2"): the name
     * without it is the second of the names the link may mean. (Two other ways SharePoint writes the address of a file are read
     * for the path in them: see filePath().) Null for anything that is not an https link to a file.
     *
     * @return array{name: string, names: array<int, string>, dir: string}|null
     */
    public static function target(string $link): ?array
    {
        if (preg_match('#^https://[^/\s]+(/[^\r\n]*)$#i', trim($link), $match) !== 1) {
            return null;
        }

        $path = rawurldecode(self::filePath($match[1]));
        $cut = strrpos($path, '/');
        $name = substr($path, $cut + 1);
        if ($name === '' || ! mb_check_encoding($path, 'UTF-8')) {
            return null;
        }

        $names = [$name];
        $plain = preg_replace('/[?#].*$/su', '', $name);
        if (is_string($plain) && $plain !== '' && $plain !== $name) {
            $names[] = $plain;
        }

        return ['name' => $name, 'names' => $names, 'dir' => rtrim(substr($path, 0, $cut), '/')];
    }

    /**
     * The part of an address (what follows the host) that holds the path of the file. A link copied from the address bar of a library
     * (".../Forms/AllItems.aspx?id=%2Fsites%2F...%2FICTCOF1.pdf&parent=...") holds it in its "id"; one made with "Copy link"
     * (".../:b:/r/sites/...") has a prefix in front of it. Any other address is its own path: a "?" in it is part of a name.
     */
    private static function filePath(string $address): string
    {
        if (preg_match('#^[^?\#]*\.aspx\?(?:[^\#]*&)?id=((?:/|%2F)[^&\#]*)#i', $address, $viewer) === 1) {
            return $viewer[1];
        }

        return preg_replace('#^/:[a-z]:/r(?=/)#i', '', $address) ?? $address;
    }

    /** What two file names are compared by: letter case does not matter, anything else does. */
    public static function nameKey(string $name): string
    {
        return mb_strtolower($name);
    }

    /** What two document numbers are compared by: "ICT/COF/123" and "ICTCOF123" are one. */
    public static function codeKey(string $text): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '', mb_strtolower($text));
    }

    /**
     * The document number a file name starts with: "ICTCOF123 - Nama Penerima.pdf" is "ICTCOF123" (what
     * comes before the separator that the names of the folder have, without the extension); the whole
     * name when it has no separator.
     */
    public static function codeOfFile(string $name, string $separator): string
    {
        $stem = preg_match('/^(.+)\.[A-Za-z0-9]{1,10}$/su', $name, $match) === 1 ? $match[1] : $name;
        $at = $separator === '' ? false : mb_strpos($stem, $separator);

        return trim($at === false || $at === 0 ? $stem : mb_substr($stem, 0, $at));
    }

    /**
     * The folder the files are in, as the path of their addresses in lower case, or null when none of
     * them has an address. (The listing of a folder is of its own files; where the folder lies in
     * SharePoint is told by them.)
     *
     * @param array<int, array{name: string, url?: ?string}> $files
     */
    public static function folderOf(array $files): ?string
    {
        $count = [];
        foreach ($files as $file) {
            $target = self::target((string) ($file['url'] ?? ''));
            if ($target !== null) {
                $dir = mb_strtolower($target['dir']);
                $count[$dir] = ($count[$dir] ?? 0) + 1;
            }
        }
        if ($count === []) {
            return null;
        }
        arsort($count);

        return (string) array_key_first($count);
    }

    /** The position of a column by its title as the workbook has it (the exact title first, then letter case and spaces aside). */
    public static function column(array $sheet, ?string $title): ?int
    {
        if ($title === null || $title === '') {
            return null;
        }

        $at = array_search($title, $sheet['columns'], true);
        if ($at !== false) {
            return (int) $at;
        }
        foreach ($sheet['columns'] as $position => $own) {
            if (mb_strtolower(trim((string) $own)) === mb_strtolower(trim($title))) {
                return (int) $position;
            }
        }

        return null;
    }

    /**
     * How each row of the sheet stands against the files of the folder.
     *
     * @param array{columns: array<int, string>, rows: array<int, array<int, string>>, lines: array<int, int>} $sheet
     * @param array<int, array{name: string, url?: ?string}> $files only the files of the folder (no folders)
     * @param array{link: string, code?: string} $columns the titles of the column with the link and of the one with the document number
     * @return array{statuses: array<int, string>, codes: array<int, string>, counts: array{total: int, ok: int, missing: int, differs: int, below: int}}|null
     *     null: the sheet has no column with that title. $codes: of each row, the document number of the file to go to as that file is
     *     named ("ICTCOF123"): the file the link names, else the one that has the row's number; '' when there is none.
     */
    public static function checkRows(array $sheet, array $files, array $columns, string $separator): ?array
    {
        $link = self::column($sheet, $columns['link'] ?? null);
        if ($link === null) {
            return null;
        }
        $code = self::column($sheet, $columns['code'] ?? null);
        $folder = self::folderOf($files);
        ['names' => $names, 'numbers' => $numbers] = self::index($files, $separator);

        $statuses = [];
        $codes = [];
        $counts = ['total' => 0, self::OK => 0, self::MISSING => 0, self::DIFFERS => 0, self::BELOW => 0];
        foreach ($sheet['rows'] as $at => $cells) {
            $target = self::target((string) ($cells[$link] ?? ''));
            $key = self::codeKey(self::rowCode($cells, $code, $target, $separator));
            $named = $target === null ? null : self::named($target, $names);
            $numbered = $key === '' ? null : ($numbers[$key] ?? null);

            [$status, $goTo] = match (true) {
                $target !== null && $folder !== null && self::isBelow($target['dir'], $folder) => [self::BELOW, $named ?? $numbered ?? ''],
                $named !== null => [self::OK, $named],
                $numbered !== null => [self::DIFFERS, $numbered],
                default => [self::MISSING, ''],
            };

            $statuses[$at] = $status;
            $codes[$at] = $goTo;
            $counts['total']++;
            $counts[$status]++;
        }

        return ['statuses' => $statuses, 'codes' => $codes, 'counts' => $counts];
    }

    /**
     * How each file stands against the rows of the sheet, and the note of the row it belongs to.
     *
     * @param array<int, array{name: string, url?: ?string}> $files only the files (no folders); the keys are kept in the answer
     * @param array{link: string, code?: string, note?: string} $columns titles of the columns with the link, the document number and the note
     * @return array<int|string, array{status: string, lines: array<int, int>, total: int, note: ?string}>|null null: the sheet has no column
     *     with the link's title. lines: the Excel rows that name the file, or have its number (the first LINES_MOST); total: how many there are
     */
    public static function checkFiles(array $sheet, array $files, array $columns, string $separator): ?array
    {
        $link = self::column($sheet, $columns['link'] ?? null);
        if ($link === null) {
            return null;
        }
        $code = self::column($sheet, $columns['code'] ?? null);
        $note = self::column($sheet, $columns['note'] ?? null);
        $folder = self::folderOf($files);

        $byName = [];
        $byCode = [];
        foreach ($sheet['rows'] as $at => $cells) {
            $target = self::target((string) ($cells[$link] ?? ''));
            // A link into a folder below does not name a file of this one (the row says "Subfolder"), whatever its last part is.
            if ($target !== null && ! ($folder !== null && self::isBelow($target['dir'], $folder))) {
                foreach ($target['names'] as $candidate) {
                    $byName[self::nameKey($candidate)][] = $at;
                }
            }
            $key = self::codeKey(self::rowCode($cells, $code, $target, $separator));
            if ($key !== '') {
                $byCode[$key][] = $at;
            }
        }

        $answer = [];
        foreach ($files as $index => $file) {
            $number = self::codeKey(self::codeOfFile($file['name'], $separator));
            [$status, $rows] = match (true) {
                isset($byName[self::nameKey($file['name'])]) => [self::LINKED, $byName[self::nameKey($file['name'])]],
                $number !== '' && isset($byCode[$number]) => [self::DIFFERS, $byCode[$number]],
                default => [self::UNLINKED, []],
            };

            $text = null;
            foreach ($rows as $at) {
                $own = $note === null ? '' : trim((string) ($sheet['rows'][$at][$note] ?? ''));
                if ($own !== '') {
                    $text = mb_strlen($own) > self::NOTE_MOST ? mb_substr($own, 0, self::NOTE_MOST) . '…' : $own;
                    break;
                }
            }

            $answer[$index] = [
                'status' => $status,
                'lines' => array_map(fn (int $at) => (int) $sheet['lines'][$at], array_slice($rows, 0, self::LINES_MOST)),
                'total' => count($rows),
                'note' => $text,
            ];
        }

        return $answer;
    }

    /**
     * What the files of the folder are known by: the document number of a file by the file's name, and the number of the first
     * file that has a document number by that number.
     *
     * @param array<int, array{name: string}> $files
     * @return array{names: array<string, string>, numbers: array<string, string>}
     */
    private static function index(array $files, string $separator): array
    {
        $names = [];
        $numbers = [];
        foreach ($files as $file) {
            $code = self::codeOfFile($file['name'], $separator);
            $names[self::nameKey($file['name'])] ??= $code;
            $key = self::codeKey($code);
            if ($key !== '') {
                $numbers[$key] ??= $code;
            }
        }

        return ['names' => $names, 'numbers' => $numbers];
    }

    /**
     * The document number of the file that a link names, or null when the folder has no file by any of the names the link may mean.
     *
     * @param array{names: array<int, string>} $target
     * @param array<string, string> $names
     */
    private static function named(array $target, array $names): ?string
    {
        foreach ($target['names'] as $candidate) {
            $key = self::nameKey($candidate);
            if (isset($names[$key])) {
                return $names[$key];
            }
        }

        return null;
    }

    /** Whether a link's folder lies below the folder of the files (not the folder itself). $folder is what folderOf() gives. */
    private static function isBelow(string $dir, string $folder): bool
    {
        $dir = mb_strtolower($dir);

        return $dir !== $folder && str_starts_with($dir . '/', $folder . '/');
    }

    /**
     * The document number of a row: the one typed in its column ("ICT/COF/123"; two spellings of one number are
     * told apart by codeKey()), else the one its link's file name starts with; '' when the row has neither. What is typed
     * and has no letter or digit in it ("-", "/") is no number.
     *
     * @param array{name: string, dir: string}|null $target
     */
    private static function rowCode(array $cells, ?int $column, ?array $target, string $separator): string
    {
        $typed = $column === null ? '' : trim((string) ($cells[$column] ?? ''));
        if (self::codeKey($typed) !== '') {
            return $typed;
        }

        return $target === null ? '' : self::codeOfFile($target['name'], $separator);
    }
}
