<?php

namespace Modules\SharePointDocs\Services;

use App\Models\UserManagement\User;
use Illuminate\Support\Facades\Log;

/**
 * Ties the page of a workbook to the page of a folder: the rows of a sheet that each point to a scan, and the
 * files of the folder those links point to (see ScanRegister for how the two are compared).
 *
 * Which sheet goes with which folder is set in config('sharepoint-docs.workbooks.<key>.scans'). The page of the
 * workbook gets a column "File scan" on that sheet; the page of the folder gets, for each file, the row of the
 * sheet that names it and that row's note. Each page adds what the other source knows only for who may open the
 * other page (MenuAccess), and only reads the other source when it is set up. Whatever goes wrong with the other
 * source (Microsoft cannot be reached, the sheet or its column is gone, no time is left) is told on the page, and
 * the page itself is shown as it always was.
 *
 * A request that needs two sources has the time of one: what the first took is taken off what the second may take.
 * The second is read in a window cut out of that time. When the first source left it less than half of what it can have beside
 * another source at the best, running out of the window says nothing about Microsoft and holds nothing back (see
 * GraphClient::shared()); otherwise the source is held back for a minute like on its own page, by its own service.
 */
class ScanLinks
{
    /** The title of the column that the sheet of the workbook gets. */
    public const COLUMN = 'File scan';

    /** Seconds a request may spend on the two sources together (the first is read on its own limit, the second on what is left). */
    private const BUDGET = 27.0;

    /** The second source is not asked for when less than this many seconds are left (and it is not at hand already). */
    private const LEAST = 3.0;

    public function __construct(
        private readonly SharePointFolder $folders,
        private readonly SharePointWorkbook $workbooks,
    ) {
    }

    /**
     * The sheets of a workbook that are tied to a folder, by the title of the sheet.
     *
     * @return array<string, array{folder: string, link: string, code?: string, note?: string}>
     */
    public static function ofWorkbook(string $workbook): array
    {
        $scans = config("sharepoint-docs.workbooks.{$workbook}.scans");

        return is_array($scans)
            ? array_filter($scans, fn ($settings) => is_array($settings) && filled($settings['folder'] ?? null) && filled($settings['link'] ?? null))
            : [];
    }

    /**
     * The sheet that lists the files of a folder (the first one that is tied to it), or null.
     *
     * @return array{workbook: string, sheet: string, folder: string, link: string, code?: string, note?: string}|null
     */
    public static function ofFolder(string $folder): ?array
    {
        foreach (array_keys((array) config('sharepoint-docs.workbooks', [])) as $workbook) {
            foreach (self::ofWorkbook((string) $workbook) as $sheet => $settings) {
                if ($settings['folder'] === $folder) {
                    return ['workbook' => (string) $workbook, 'sheet' => (string) $sheet] + $settings;
                }
            }
        }

        return null;
    }

    /**
     * The moment a request starts needing its sources (to be handed back to addToBook() / forFolder()): when the request
     * began, if the server says so (what the framework needed before the controller ran is part of the request's time). Only
     * when that was less than 10 seconds ago: a moment further back is that of a worker that stays up, not of this request.
     */
    public function begin(): float
    {
        $now = self::clock();
        $began = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        if (is_float($began)) {
            $before = microtime(true) - $began;
            if ($before > 0 && $before < 10) {
                return $now - $before;
            }
        }

        return $now;
    }

    /**
     * Adds, to the sheet of a workbook that is on screen, when it is tied to a folder, a last column "File scan" that says for
     * every row whether its file is in the folder. (It is a column like the others: it is sorted, but a search does not look in
     * it: the cards over the table are for that.) The other sheets are not looked at: they have no such column to show.
     *
     * @param array $book what SharePointWorkbook::tables() returned; the sheet is changed in it (it is a copy, not what is kept)
     * @param int $shown the position of the sheet that is on screen
     * @return array<int, array> by the position of the sheet either ['column', 'statuses', 'codes', 'counts', 'fetched_at', 'stale', 'folder',
     *     'open'], or ['unavailable' => why]
     */
    public function addToBook(string $workbook, array &$book, ?User $user, bool $refresh, float $started, int $shown): array
    {
        $scans = [];
        foreach (self::ofWorkbook($workbook) as $title => $settings) {
            $index = self::sheetNamed($book['sheets'], (string) $title);
            $folder = (string) $settings['folder'];
            if ($index === null
                || $index !== $shown
                || ! is_array(config("sharepoint-docs.folders.{$folder}"))
                || ! MenuAccess::allows($user, "documents.{$folder}.list")
                || ! $this->folders->configured($folder)) {
                continue;
            }

            try {
                $listing = $this->listing($folder, $refresh, $started, (bool) ($book['stale'] ?? false));
            } catch (GraphException $e) {
                $scans[$index] = ['unavailable' => __('SharePointDocs::links.column_failed', ['column' => self::COLUMN, 'why' => $e->userMessage()])];

                continue;
            }
            if ($listing['truncated']) {
                $scans[$index] = ['unavailable' => __('SharePointDocs::links.column_too_many', ['column' => self::COLUMN, 'folder' => self::folderTitle($folder)])];

                continue;
            }

            $files = array_values(array_filter($listing['items'], fn (array $item) => ! $item['folder']));
            $result = ScanRegister::checkRows($book['sheets'][$index], $files, $settings, self::separator($folder));
            if ($result === null) {
                $scans[$index] = ['unavailable' => __('SharePointDocs::links.column_no_link', ['link' => $settings['link'], 'sheet' => $title])];

                continue;
            }

            $column = count($book['sheets'][$index]['columns']);
            $book['sheets'][$index]['columns'][] = self::COLUMN;
            foreach ($result['statuses'] as $at => $status) {
                $cells = $book['sheets'][$index]['rows'][$at];
                $cells = array_pad($cells, $column, '');
                $cells[$column] = ScanRegister::TEXT[$status];
                $book['sheets'][$index]['rows'][$at] = $cells;
            }

            $scans[$index] = [
                'column' => $column,
                'statuses' => $result['statuses'],
                'codes' => $result['codes'],
                'counts' => $result['counts'],
                'fetched_at' => $listing['fetched_at'],
                'stale' => (bool) $listing['stale'],
                'folder' => self::folderTitle($folder),
                'open' => route("documents.{$folder}.index", [], false),
            ];
        }

        return $scans;
    }

    /**
     * What the page of a folder offers beside its files: the sheet that lists them. Null when the folder is not tied
     * to a sheet, the reader may not open the workbook's page, or the workbook is not set up.
     *
     * @return array{title: string, sheet: string, open: string}|null
     */
    public function offeredOnFolder(string $folder, ?User $user): ?array
    {
        $settings = self::ofFolder($folder);
        if ($settings === null || ! MenuAccess::allows($user, "documents.{$settings['workbook']}.rows") || ! $this->workbooks->configured($settings['workbook'])) {
            return null;
        }

        return [
            'title' => (string) config("sharepoint-docs.workbooks.{$settings['workbook']}.title", $settings['workbook']),
            'sheet' => $settings['sheet'],
            'open' => route("documents.{$settings['workbook']}.index", [], false),
        ];
    }

    /**
     * For the files of one listing of the folder: the row of the sheet that names each (or has its number) and the
     * note of that row.
     *
     * @param array<int, array> $items the items of the listing (files and folders); the answer is by their position
     * @param bool $firstStale the listing came from what was kept because Microsoft could not be reached
     * @return array{title: string, sheet: string, open: string, files?: array<int, array{status: string, lines: array<int, int>, total: int, note: ?string}>, fetched_at?: int, stale?: bool, unavailable?: string}|null
     *     null: nothing is offered (see offeredOnFolder())
     */
    public function forFolder(string $folder, array $items, ?User $user, bool $refresh, float $started, bool $firstStale = false): ?array
    {
        $offered = $this->offeredOnFolder($folder, $user);
        if ($offered === null) {
            return null;
        }
        $settings = self::ofFolder($folder);
        $workbook = $settings['workbook'];

        try {
            $book = $this->book($workbook, $refresh, $started, $firstStale);
        } catch (GraphException $e) {
            return $offered + ['unavailable' => __('SharePointDocs::links.register_failed', ['why' => $e->workbookMessage()])];
        }

        $index = self::sheetNamed($book['sheets'], $settings['sheet']);
        if ($index === null) {
            return $offered + ['unavailable' => __('SharePointDocs::links.register_no_sheet', ['sheet' => $settings['sheet'], 'workbook' => $offered['title']])];
        }
        if ($book['sheets'][$index]['truncated']) {
            return $offered + ['unavailable' => __('SharePointDocs::links.register_too_long', ['sheet' => $settings['sheet']])];
        }

        $files = array_filter($items, fn (array $item) => ! $item['folder']);
        $result = ScanRegister::checkFiles($book['sheets'][$index], $files, $settings, self::separator($folder));
        if ($result === null) {
            return $offered + ['unavailable' => __('SharePointDocs::links.register_no_link', ['link' => $settings['link'], 'sheet' => $settings['sheet']])];
        }

        return $offered + ['files' => $result, 'fetched_at' => (int) $book['fetched_at'], 'stale' => (bool) $book['stale']];
    }

    /** What is in the folder, read on the time that is left of the request's. */
    private function listing(string $folder, bool $refresh, float $started, bool $firstStale): array
    {
        return $this->second(
            $this->folders->fresh($folder, '', $refresh),
            (float) config('sharepoint-docs.time_limit', 20),
            0.0,
            $started,
            $firstStale,
            fn (float $time, callable $reading, bool $cut) => $this->folders->within($time, $reading, $cut),
            fn () => $this->folders->listing($folder, '', $refresh),
            "The files of folder \"{$folder}\" could not be read to check a sheet against them.",
        );
    }

    /** The workbook as tables, read on the time that is left of the request's. */
    private function book(string $workbook, bool $refresh, float $started, bool $firstStale): array
    {
        return $this->second(
            $this->workbooks->fresh($workbook, $refresh),
            (float) config('sharepoint-docs.preview_time_limit', 25),
            // What it needs after the download, at the least, to read the workbook (see SharePointWorkbook): kept out of the
            // time the download may take, so that the whole stays within what the request has.
            (float) SharePointWorkbook::PARSE_LEAST,
            $started,
            $firstStale,
            fn (float $time, callable $reading, bool $cut) => $this->workbooks->within($time, $reading, $cut),
            fn () => $this->workbooks->tables($workbook, $refresh),
            "The workbook \"{$workbook}\" could not be read to check the files of a folder against it.",
        );
    }

    /**
     * Reads the second source of a request. Nothing is asked of Microsoft for it when what is at hand is recent enough (it costs no
     * time), nor when the first source could not be reached (the second would only add its own wait to that), nor when less than LEAST
     * seconds of the request's time are left: it would only be given up half way. A failure is written to the log.
     *
     * Whether Microsoft could not be reached for the source is decided as on its own page, by its service (a mark that holds the source
     * back for a minute), unless the first source left it too little time. That is: a window of less than half of the best it can have
     * beside another source, which is the whole of the BUDGET but what is kept for the reading afterwards, and no more than its own page
     * gives it. Running out of such a window says nothing about Microsoft, and the service is told so ($cut).
     *
     * @param float $own the time this source gets on its own page
     * @param float $reserve what the source needs of the time after the part that Microsoft is asked for (not when it is at hand)
     * @param callable $within runs a reading on a time: (seconds, reading, whether the time is cut) => the reading's answer
     */
    private function second(bool $fresh, float $own, float $reserve, float $started, bool $firstStale, callable $within, callable $read, string $failed): array
    {
        // (A source that is at hand asks Microsoft for nothing: only its window has to exist, whatever is left.)
        $left = self::BUDGET - (self::clock() - $started) - $reserve;
        if (! $fresh) {
            if ($firstStale) {
                throw new GraphException(GraphException::UNAVAILABLE, 'The other source is not asked for now: Microsoft could not be reached a moment ago.', 0, true);
            }
            if ($left < self::LEAST) {
                throw new GraphException(GraphException::OUT_OF_TIME, 'The time of the request is used up by the first source.', 0, true);
            }
        }

        $window = max(0.0, min($own, $left));
        $cut = $window < min($own, self::BUDGET - $reserve) / 2;

        try {
            return $within($window, $read, $cut);
        } catch (GraphException $e) {
            Log::warning($failed, ['reason' => $e->reason, 'detail' => $e->getMessage()]);

            throw $e;
        }
    }

    /** The position of the sheet with this title (letter case and spaces around it aside), or null. */
    private static function sheetNamed(array $sheets, string $title): ?int
    {
        foreach ($sheets as $at => $sheet) {
            if (mb_strtolower(trim((string) $sheet['name'])) === mb_strtolower(trim($title))) {
                return (int) $at;
            }
        }

        return null;
    }

    private static function folderTitle(string $folder): string
    {
        return (string) config("sharepoint-docs.folders.{$folder}.title", $folder);
    }

    /**
     * What separates the number from the rest in the names of the folder's files: the folder's own setting, else the one the names of
     * the files have. (The page of the folder finds the files of a number by the same: see FolderController::list().)
     */
    public static function separator(string $folder): string
    {
        return (string) config("sharepoint-docs.folders.{$folder}.name_split.separator", ' - ');
    }

    private static function clock(): float
    {
        return now()->getPreciseTimestamp() / 1_000_000;
    }
}
