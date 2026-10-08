<?php

namespace Modules\SharePointDocs\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Excel workbooks of config('sharepoint-docs.workbooks'): a workbook in SharePoint, read as
 * tables (one per sheet) to be shown as a page.
 *
 * The workbook is found from the address in the config (the link as it stands in the browser
 * while the workbook is open), downloaded with the application's own Microsoft identity and
 * read by WorkbookReader. What was read is kept the same way a folder listing is: used as it is
 * for a few minutes, read again on "Muat ulang", and kept for a day to fall back on while
 * Microsoft cannot be reached. A workbook that has not changed since it was kept is not
 * downloaded again, and one that could not be read is not downloaded again until it changes, or
 * the settings that decided it change, or sharepoint-docs.cache_minutes have passed.
 */
class SharePointWorkbook
{
    /** "Muat ulang" asks SharePoint again only when the copy at hand is at least this old (seconds). */
    private const REFRESH_AFTER = 15;

    /** After Microsoft could not be reached, it is not asked for the workbook again for this long (seconds). */
    private const RETRY_AFTER = 60;

    /** How long what was read is kept to fall back on when Microsoft cannot be reached. */
    private const KEEP_HOURS = 24;

    /** Changes with the shape of what is kept: a copy kept by another version is read again. */
    private const FORMAT = 2;

    /** Reading a downloaded workbook may take at most this long, and gets at least this long whatever is left of the time for the whole (seconds). */
    private const PARSE_MOST = 15;

    private const PARSE_LEAST = 5;

    public function __construct(private readonly GraphClient $graph, private readonly WorkbookReader $reader)
    {
    }

    /** The address of the workbook as configured, or '' when it is not set. */
    public function address(string $key): string
    {
        return trim((string) config("sharepoint-docs.workbooks.{$key}.url"));
    }

    public function configured(string $key): bool
    {
        return $this->address($key) !== '' && $this->graph->configured();
    }

    /**
     * The workbook as tables.
     *
     * @return array{
     *     fetched_at: int, stale: bool, name: string, modified: ?int, web_url: ?string,
     *     sheets: array<int, array{name: string, truncated: bool, columns: array<int, string>,
     *         rows: array<int, array<int, string>>, numbers: array<int, array<int, float>>, lines: array<int, int>}>
     * } stale: what is shown is a copy kept earlier, because the new one could not be had
     */
    public function tables(string $key, bool $refresh = false): array
    {
        if (! $this->configured($key)) {
            throw new GraphException(GraphException::NOT_CONFIGURED, "Workbook \"{$key}\" has no address, or services.azure is incomplete.");
        }

        $address = $this->address($key);
        $shown = $this->shown($key);
        // What is kept depends on the choice of sheets and rows as well: after the settings change the old
        // choice is not shown, and two pages for one workbook with different choices do not share a copy.
        $cacheKey = $this->cacheKey('book', $address . "\n" . $shown);
        $unreachable = $this->cacheKey('book-unreachable', $address);
        $kept = Cache::get($cacheKey);
        $kept = is_array($kept) && ($kept['format'] ?? null) === self::FORMAT && isset($kept['fetched_at'], $kept['sheets']) ? $kept : null;

        if ($kept !== null) {
            $age = now()->getTimestamp() - (int) $kept['fetched_at'];
            $wanted = $refresh ? self::REFRESH_AFTER : 60 * max(1, (int) config('sharepoint-docs.cache_minutes', 10));
            // A copy dated in the future (the server clock was set back) is not taken for a fresh one.
            if ($age >= 0 && $age < $wanted) {
                return $kept + ['stale' => false];
            }
        }

        // The mark holds the reason Microsoft is not asked: a file that was too slow stays "too slow".
        if (is_string($before = Cache::get($unreachable))) {
            if ($kept !== null) {
                return $kept + ['stale' => true];
            }
            throw new GraphException(
                $before === GraphException::TOO_SLOW ? GraphException::TOO_SLOW : GraphException::UNAVAILABLE,
                'Microsoft could not be reached, or was too slow, less than ' . self::RETRY_AFTER . ' seconds ago and is not asked again yet.'
            );
        }

        try {
            $book = $this->graph->within(
                max(0, (float) config('sharepoint-docs.preview_time_limit', 25)),
                fn () => $this->fetch($key, $address, $kept, $shown)
            );
        } catch (GraphException $e) {
            if (! in_array($e->reason, [GraphException::UNAVAILABLE, GraphException::TOO_SLOW], true)) {
                // A definite answer (no permission, not there, not a workbook any more, too large): what was
                // kept must not come back at the next hiccup either.
                Cache::forget($cacheKey);
                throw $e;
            }

            Cache::put($unreachable, $e->reason, self::RETRY_AFTER);
            if ($kept === null) {
                throw $e;
            }
            Log::warning('SharePoint could not be reached, showing the kept workbook.', ['workbook' => $key, 'detail' => $e->getMessage()]);

            return $kept + ['stale' => true];
        }

        Cache::put($cacheKey, $book, now()->addHours(self::KEEP_HOURS));
        Cache::forget($unreachable);
        Cache::forget($this->cacheKey('book-failed', $address));

        return $book + ['stale' => false];
    }

    /**
     * Reads the workbook from SharePoint. $kept: what is kept now; when the file has not changed
     * since, its tables are taken over and only the time of reading is renewed.
     */
    private function fetch(string $key, string $address, ?array $kept, string $shown): array
    {
        try {
            $item = $this->graph->get('/shares/' . self::shareId($address) . '/driveItem');
        } catch (GraphException $e) {
            // Graph's own 400: it does not recognise the address as something in SharePoint at all.
            throw $e->status === 400 && $e->reason === GraphException::UNAVAILABLE
                ? new GraphException(GraphException::NOT_FOUND, $e->getMessage(), 400)
                : $e;
        }

        $driveId = $item['parentReference']['driveId'] ?? null;
        $itemId = $item['id'] ?? null;
        $name = is_string($item['name'] ?? null) ? $item['name'] : '';
        if (! self::isId($driveId) || ! self::isId($itemId)) {
            throw new GraphException(GraphException::UNAVAILABLE, 'Graph described the workbook without usable ids.');
        }
        if (! isset($item['file']) || ! str_ends_with(strtolower($name), '.xlsx')) {
            throw new GraphException(GraphException::UNSUPPORTED, 'The configured address is not an .xlsx workbook.');
        }
        $limit = max(1, (int) config('sharepoint-docs.workbook_max_mb', 15)) * 1024 * 1024;
        if (is_numeric($item['size'] ?? null) && (int) $item['size'] > $limit) {
            throw new GraphException(GraphException::TOO_LARGE, 'The workbook is larger than what is read into the application.');
        }

        $facts = [
            'format' => self::FORMAT,
            'fetched_at' => now()->getTimestamp(),
            'name' => $name,
            'modified' => self::timestamp($item['lastModifiedDateTime'] ?? null),
            'web_url' => is_string($item['webUrl'] ?? null) && str_starts_with($item['webUrl'], 'https://') ? $item['webUrl'] : null,
            // Changes with every saved version of the file.
            'version' => is_string($item['eTag'] ?? null) ? $item['eTag'] : null,
        ];
        if ($kept !== null && $facts['version'] !== null && ($kept['version'] ?? null) === $facts['version']) {
            return $facts + ['sheets' => $kept['sheets']];
        }
        $this->refuseWhatFailedBefore($address, $facts['version'], $shown);

        // What the reading was given: nothing until the download is done.
        $seconds = 0.0;
        try {
            $source = $this->graph->locate("/drives/{$driveId}/items/{$itemId}/content");
            if ($source['body'] !== null && strlen($source['body']) > $limit) {
                throw new GraphException(GraphException::TOO_LARGE, 'The workbook is larger than what is read into the application.');
            }
            $bytes = $source['body'] ?? $this->graph->fetch((string) $source['address'], $limit);

            // What is left of the time for the whole, but not less than it takes to read an ordinary workbook many times over.
            $seconds = max(self::PARSE_LEAST, min(self::PARSE_MOST, $this->graph->timeLeft()));
            $sheets = $this->pick($key, $this->reader->read(
                $bytes,
                $this->skipsUnusedRows($key),
                max(1, (int) config('sharepoint-docs.workbook_max_cells', XlsxReader::MAX_CELLS)),
                $seconds
            ));
        } catch (GraphException $e) {
            // This version of the file is what it is (too large, not a workbook): it is not downloaded and read
            // again for every click, only when it has changed or the time to try again has come. Likewise one that
            // used up all the time a reading may have: it is too heavy for this server. (A reading that ran out of a
            // time that the download had shortened proves nothing about the file; it is held back for a minute, like
            // any slowness, and then tried again.)
            $verdict = in_array($e->reason, [GraphException::UNSUPPORTED, GraphException::TOO_LARGE], true)
                || ($e->reason === GraphException::TOO_SLOW && $seconds >= self::PARSE_MOST);
            if ($facts['version'] !== null && $verdict) {
                Cache::put(
                    $this->cacheKey('book-failed', $address),
                    [
                        'at' => now()->getTimestamp(), 'reason' => $e->reason, 'detail' => $e->getMessage(),
                        'version' => $facts['version'], 'shown' => $shown, 'limits' => $this->limits(),
                    ],
                    now()->addMinutes(max(1, (int) config('sharepoint-docs.cache_minutes', 10)))
                );
            }

            throw $e;
        }

        return $facts + ['sheets' => $sheets];
    }

    /** The same version of the workbook could not be read a moment ago (see fetch()): the same answer, without asking for the file. */
    private function refuseWhatFailedBefore(string $address, ?string $version, string $shown): void
    {
        $failed = Cache::get($this->cacheKey('book-failed', $address));
        // What was decided by settings is decided again when they have changed (a limit that was raised, a reader that was improved).
        if (! is_array($failed) || $version === null || ($failed['version'] ?? null) !== $version || ($failed['shown'] ?? null) !== $shown || ($failed['limits'] ?? null) !== $this->limits()) {
            return;
        }

        $age = now()->getTimestamp() - (int) ($failed['at'] ?? 0);
        if ($age >= 0 && $age < 60 * max(1, (int) config('sharepoint-docs.cache_minutes', 10)) && is_string($failed['reason'] ?? null)) {
            throw new GraphException($failed['reason'], "This version of the workbook could not be read {$age} seconds ago and is not read again yet: " . ($failed['detail'] ?? ''));
        }
    }

    /** What decides whether a workbook is too large to read, as one value: the settings, the limits of the reader, the memory a script may use. */
    private function limits(): string
    {
        return sha1((string) json_encode([
            XlsxReader::limits(),
            'cells' => max(1, (int) config('sharepoint-docs.workbook_max_cells', XlsxReader::MAX_CELLS)),
            'megabytes' => max(1, (int) config('sharepoint-docs.workbook_max_mb', 15)),
            'rows' => WorkbookReader::MAX_ROWS,
            'columns' => WorkbookReader::MAX_COLUMNS,
            'memory' => (string) ini_get('memory_limit'),
        ]));
    }

    /**
     * The sheets that are shown: those named in the config ("sheets"), in that order, or without
     * such a list every sheet that is visible in Excel.
     */
    private function pick(string $key, array $tables): array
    {
        $names = config("sharepoint-docs.workbooks.{$key}.sheets");
        $picked = [];
        if (is_array($names) && $names !== []) {
            foreach ($names as $name) {
                foreach ($tables as $table) {
                    if (is_string($name) && strcasecmp($table['name'], $name) === 0) {
                        $picked[] = $table;
                    }
                }
            }
        } else {
            $picked = array_values(array_filter($tables, fn (array $table) => ! $table['hidden']));
        }

        return array_map(fn (array $table) => array_diff_key($table, ['hidden' => true]), $picked);
    }

    /** Whether the rows of a register that hold nothing but their number yet are left out (see WorkbookReader::read). */
    private function skipsUnusedRows(string $key): bool
    {
        return (bool) config("sharepoint-docs.workbooks.{$key}.skip_unused_rows", false);
    }

    /** What the config asks to be shown (which rows, which sheets), as one value: a copy kept for another choice is another copy. */
    private function shown(string $key): string
    {
        $names = config("sharepoint-docs.workbooks.{$key}.sheets");

        return ($this->skipsUnusedRows($key) ? 'rows in use' : 'every row') . "\n"
            . (is_array($names) && $names !== [] ? implode("\n", array_map('strval', $names)) : '*');
    }

    private static function timestamp(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (Throwable) {
            return null;
        }
    }

    private static function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9!_.\-]{1,200}$/', $id) === 1;
    }

    /** Graph's encoding of an address for /shares/{id}: "u!" + unpadded base64url. */
    private static function shareId(string $address): string
    {
        return 'u!' . rtrim(strtr(base64_encode($address), '+/', '-_'), '=');
    }

    private function cacheKey(string $kind, string $address): string
    {
        return "sharepoint-docs:{$kind}:" . sha1($address);
    }
}
