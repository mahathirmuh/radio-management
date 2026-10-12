<?php

namespace Modules\SharePointDocs\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The folders of config('sharepoint-docs.folders') and what is in them.
 *
 * A folder is looked up from the address in the config (Graph's "shares" lookup accepts the
 * link as copied from the browser) and its ids are remembered for a day; when it is renamed or
 * moved, the address in the config has to follow. Listings are kept for a few minutes: the
 * page searches, sorts and pages through the kept copy instead of asking Microsoft on every
 * click.
 */
class SharePointFolder
{
    /** "Muat ulang" asks SharePoint again only when the copy at hand is at least this old (seconds). */
    private const REFRESH_AFTER = 15;

    /** After Microsoft could not be reached (for the folder, a folder below, or one file), it is not asked for that again for this long (seconds). */
    private const RETRY_AFTER = 60;

    /** How long a folder's ids are remembered. */
    private const ROOT_HOURS = 24;

    /** How long a listing is kept to fall back on when Microsoft cannot be reached. */
    private const KEEP_HOURS = 24;

    private const FIELDS = 'id,name,size,file,folder,lastModifiedDateTime,lastModifiedBy,webUrl';

    /** Changes with the shape of a kept listing: a copy kept by an older version is read again. */
    private const FORMAT = 2;

    /**
     * What is shown inside the application, and as which content type: only kinds a browser
     * displays by itself without running anything from them (no HTML, no SVG, no Office files).
     */
    private const PREVIEWABLE = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    public function __construct(private readonly GraphClient $graph)
    {
    }

    /** The address of the folder as configured, or '' when it is not set. */
    public function address(string $key): string
    {
        return trim((string) config("sharepoint-docs.folders.{$key}.url"));
    }

    public function configured(string $key): bool
    {
        return $this->address($key) !== '' && $this->graph->configured();
    }

    /**
     * Runs $reading, which reads from this service, in at most $seconds: the time allowed for everything it
     * asks Microsoft (see GraphClient::within). For a page that has another source to read in the same request
     * and cannot spend more than what is left of its time on this one.
     */
    public function within(float $seconds, callable $reading, bool $shared = false): mixed
    {
        return $this->graph->within($seconds, $reading, $shared);
    }

    /**
     * Whether a listing that is recent enough to be used as it is (listing() would not ask Microsoft) is at hand: as
     * recent as sharepoint-docs.cache_minutes, or, after "Muat ulang" ($refresh), as recent as that asks for.
     */
    public function fresh(string $key, string $path = '', bool $refresh = false): bool
    {
        if (! $this->configured($key)) {
            return false;
        }
        $kept = $this->kept($this->address($key), $path);
        if ($kept === null) {
            return false;
        }

        $age = now()->getTimestamp() - (int) $kept['fetched_at'];

        return $age >= 0 && $age < $this->wanted($refresh);
    }

    /** How old (seconds) a kept listing may be to be used as it is. */
    private function wanted(bool $refresh): int
    {
        return $refresh ? self::REFRESH_AFTER : 60 * max(1, (int) config('sharepoint-docs.cache_minutes', 10));
    }

    /**
     * What is in the folder, or in a folder below it ($path as returned by cleanPath()).
     *
     * Microsoft is asked only when the kept copy is too old, and not again for RETRY_AFTER
     * seconds after it could not be reached (for the folder: by anyone, for any path; for a
     * folder below: for that path). Until then the kept copy is returned marked "stale", or
     * the failure is repeated when there is none.
     *
     * @return array{
     *     fetched_at: int, truncated: bool, stale: bool,
     *     items: array<int, array{id: ?string, name: string, folder: bool, size: int, modified: ?int, modified_by: ?string, url: ?string}>
     * }
     */
    public function listing(string $key, string $path = '', bool $refresh = false): array
    {
        if (! $this->configured($key)) {
            throw new GraphException(GraphException::NOT_CONFIGURED, "Folder \"{$key}\" has no address, or services.azure is incomplete.");
        }

        $address = $this->address($key);
        $cacheKey = $this->cacheKey('list', $address, $path);
        $kept = $this->kept($address, $path);

        if ($kept !== null) {
            $age = now()->getTimestamp() - (int) $kept['fetched_at'];
            // A copy dated in the future (the server clock was set back) is not taken for a fresh one.
            if ($age >= 0 && $age < $this->wanted($refresh)) {
                return $kept + ['stale' => false];
            }
        }

        if ($this->unreachable($address, $path)) {
            if ($kept !== null) {
                return $kept + ['stale' => true];
            }
            throw new GraphException(GraphException::UNAVAILABLE, 'Microsoft could not be reached less than ' . self::RETRY_AFTER . ' seconds ago and is not asked again yet.');
        }

        try {
            $listing = $this->graph->within(max(0, (float) config('sharepoint-docs.time_limit', 20)), function () use ($key, $address, $path) {
                $this->mustBeListedAbove($key, $path);

                return $this->fetch($this->root($address), $path);
            });
        } catch (GraphException $e) {
            if ($e->reason !== GraphException::UNAVAILABLE) {
                // A definite answer (no permission, not there, sign-in refused): what was kept
                // must not come back at the next hiccup either. Dropping it takes the kept
                // listings of the folders below with it (see kept()).
                Cache::forget($cacheKey);
                if ($e->reason === GraphException::NOT_FOUND && $path === '') {
                    // The folder may have been replaced by another one at the same address.
                    Cache::forget($this->cacheKey('root', $address));
                }
                throw $e;
            }

            // For the folder itself this is the mark that holds every request back; for a
            // folder below, only requests for that one (its trouble may be its own). Not when the
            // time ran out that a page cut out of its own for this listing and made much shorter than
            // usual (it has another source to read too): that says nothing about Microsoft, and would
            // hold back the folder's own page.
            if (! ($e->outOfTime && $this->graph->shared())) {
                Cache::put($this->cacheKey('unreachable', $address, $path), true, self::RETRY_AFTER);
            }
            if ($kept === null) {
                throw $e;
            }
            Log::warning('SharePoint could not be reached, showing the kept listing.', ['folder' => $key, 'detail' => $e->getMessage()]);

            return $kept + ['stale' => true];
        }

        Cache::put($cacheKey, $listing, now()->addHours(self::KEEP_HOURS));
        Cache::forget($this->cacheKey('unreachable', $address, $path));

        return $listing + ['stale' => false];
    }

    /**
     * A file of the folder, to be shown inside the application: its name, the content type it
     * is sent as, and its bytes.
     *
     * Only a file that the listing of its folder shows is read (by its id there, so nothing
     * outside the folder can be asked for), only the kinds of PREVIEWABLE (and the bytes must
     * start like that kind), and only up to sharepoint-docs.preview_max_mb.
     *
     * Everything it needs from Microsoft (the listing when that has to be read again, the
     * sign-in, the file) shares sharepoint-docs.preview_time_limit seconds.
     *
     * @return array{name: string, type: string, body: string}
     */
    public function file(string $key, string $path, string $id): array
    {
        return $this->graph->within(
            max(0, (float) config('sharepoint-docs.preview_time_limit', 25)),
            fn () => $this->fileOf($key, $path, $id)
        );
    }

    private function fileOf(string $key, string $path, string $id): array
    {
        $file = null;
        foreach ($this->listing($key, $path)['items'] as $item) {
            if (! $item['folder'] && $item['id'] !== null && $item['id'] === $id) {
                $file = $item;
                break;
            }
        }
        if ($file === null) {
            throw new GraphException(GraphException::NOT_FOUND, 'The listing has no file with the requested id.');
        }

        $type = self::previewType($file['name']);
        if ($type === null) {
            throw new GraphException(GraphException::UNSUPPORTED, 'Files of this kind are not shown inside the application.');
        }
        $limit = self::previewLimit();
        if ($file['size'] > $limit) {
            throw new GraphException(GraphException::TOO_LARGE, 'The file is larger than what is shown inside the application.');
        }

        // Not while Microsoft could not be reached a moment ago, and not this file again right
        // after it failed: a file's own trouble (a slow, big or broken one) holds back neither
        // the list nor the other files, and is repeated as what it was.
        $address = $this->address($key);
        $unreachable = $this->cacheKey('unreachable', $address);
        $failed = $this->cacheKey('unreachable', $address, $path . "\n" . $id);
        if (Cache::has($unreachable)) {
            throw new GraphException(GraphException::UNAVAILABLE, 'Microsoft could not be reached less than ' . self::RETRY_AFTER . ' seconds ago and is not asked again yet.');
        }
        if (($before = Cache::get($failed)) !== null) {
            throw new GraphException(
                $before === GraphException::TOO_SLOW ? GraphException::TOO_SLOW : GraphException::UNAVAILABLE,
                'This file could not be fetched less than ' . self::RETRY_AFTER . ' seconds ago and is not asked for again yet.'
            );
        }

        // Two stops: Graph (sign-in, where the folder is, where the file's bytes are), then the
        // one-time address the bytes come from.
        $atGraph = true;
        try {
            $root = $this->root($address);
            $source = $this->graph->locate("/drives/{$root['drive_id']}/items/{$id}/content");
            $atGraph = false;
            // fetch() keeps to the limit itself; bytes that Graph sent along are measured here.
            if ($source['body'] !== null && strlen($source['body']) > $limit) {
                throw new GraphException(GraphException::TOO_LARGE, 'The file is larger than what is shown inside the application.');
            }
            $body = $source['body'] ?? $this->graph->fetch((string) $source['address'], $limit);
        } catch (GraphException $e) {
            if ($e->reason === GraphException::UNAVAILABLE && $atGraph && $e->status === 0 && ! $e->outOfTime) {
                // Graph had the time a request gets and nothing of Microsoft's that can be used
                // came back (no connection, no sign-in, something in between answering, an
                // answer that makes no sense): that is not this file's trouble. Nothing is
                // asked for a while.
                Cache::put($unreachable, true, self::RETRY_AFTER);
            } elseif (in_array($e->reason, [GraphException::UNAVAILABLE, GraphException::TOO_SLOW], true)) {
                // This file's own trouble, or its time was used up before Graph had its say
                // (a slow re-read of the list before it): only this file waits.
                Cache::put($failed, $e->reason, self::RETRY_AFTER);
            } elseif (in_array($e->reason, [GraphException::NOT_FOUND, GraphException::FORBIDDEN, GraphException::CREDENTIALS], true)) {
                // The listing that showed this file is out of date: it is read again next time.
                Cache::forget($this->cacheKey('list', $address, $path));
            }
            throw $e;
        }

        if (! self::startsLike($type, $body)) {
            throw new GraphException(GraphException::UNSUPPORTED, 'The bytes of the file are not what its name says.');
        }

        return ['name' => $file['name'], 'type' => $type, 'body' => $body];
    }

    /** "pdf" for "ICTCOF1 - Nama.PDF"; '' for a name without an extension. */
    public static function extension(string $name): string
    {
        return preg_match('/^.+\.([A-Za-z0-9]{1,10})$/s', $name, $match) === 1 ? strtolower($match[1]) : '';
    }

    /** The content type a file of this name is shown as inside the application; null when it is not shown there. */
    public static function previewType(string $name): ?string
    {
        return self::PREVIEWABLE[self::extension($name)] ?? null;
    }

    /** The largest file that is shown inside the application, in bytes. */
    public static function previewLimit(): int
    {
        return max(1, (int) config('sharepoint-docs.preview_max_mb', 10)) * 1024 * 1024;
    }

    /**
     * The configured address as a link for the page, when it is one that may be shown to every
     * reader: the address of a library view (…/Forms/AllItems.aspx?id=…), which SharePoint
     * opens only for who has access there. Any other address still finds the folder but is
     * never shown, because a sharing link gives access to whoever holds it.
     */
    public function link(string $key): ?string
    {
        $address = self::https($this->address($key));
        $path = $address === null ? null : parse_url($address, PHP_URL_PATH);

        return is_string($path) && preg_match('#/Forms/[^/]+\.aspx$#i', $path) === 1 ? $address : null;
    }

    /**
     * The folder's own page in SharePoint ($path: a folder below it), or null when unknown.
     * Never asks Microsoft: it uses what a listing has already found out.
     */
    public function webUrl(string $key, string $path = ''): ?string
    {
        // The configured view is what the owner looks at (their columns and sort order).
        if ($path === '' && $this->link($key) !== null) {
            return $this->link($key);
        }

        $root = $this->knownRoot($this->address($key))['web_url'] ?? null;

        return $root === null || $path === '' ? $root : rtrim($root, '/') . '/' . self::encodePath($path);
    }

    /**
     * A path below the folder as "a/b/c", '' for the folder itself, or null when it is not a
     * path that can exist in SharePoint (and so nothing that should be sent to Graph).
     */
    public static function cleanPath(mixed $path): ?string
    {
        if ($path === null) {
            return '';
        }
        if (! is_string($path) || ! mb_check_encoding($path, 'UTF-8')) {
            return null;
        }

        $path = trim(trim($path), '/');
        if ($path === '') {
            return '';
        }
        if (mb_strlen($path) > 400 || preg_match('/[\x00-\x1F\x7F\\\\:*?"<>|]/', $path)) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || trim($segment) !== $segment) {
                return null;
            }
        }

        return $path;
    }

    /**
     * A folder below is read only when the listing above it shows a folder of exactly that
     * name: a made-up path gets its "not found" here, without a request to Microsoft.
     */
    private function mustBeListedAbove(string $key, string $path): void
    {
        if ($path === '') {
            return;
        }

        $cut = strrpos($path, '/');
        [$above, $name] = $cut === false ? ['', $path] : [substr($path, 0, $cut), substr($path, $cut + 1)];

        $listing = $this->listing($key, $above);
        foreach ($listing['items'] as $item) {
            if ($item['folder'] && $item['name'] === $name) {
                if ($listing['stale']) {
                    // Microsoft could not be reached for the folder above just now: it is not
                    // asked for the one below in the same breath.
                    throw new GraphException(GraphException::UNAVAILABLE, 'The folder above could only be shown from its kept copy.');
                }

                return;
            }
        }

        throw new GraphException(GraphException::NOT_FOUND, 'The listing above the requested path has no folder of that name.');
    }

    /**
     * The kept listing of a path, when it may still be used: one of the current format, and for
     * a folder below only while the listings above it are still kept. So when a listing is
     * dropped (permission withdrawn, folder gone), everything kept below it goes with it.
     */
    private function kept(string $address, string $path): ?array
    {
        $kept = Cache::get($this->cacheKey('list', $address, $path));
        if (! is_array($kept) || ($kept['format'] ?? null) !== self::FORMAT || ! isset($kept['fetched_at'], $kept['items'])) {
            return null;
        }

        if ($path !== '') {
            $cut = strrpos($path, '/');
            if ($this->kept($address, $cut === false ? '' : substr($path, 0, $cut)) === null) {
                return null;
            }
        }

        return $kept;
    }

    /**
     * Whether Microsoft is not to be asked for this path right now: it could not be reached for
     * the folder as a whole, or for this very path, less than RETRY_AFTER seconds ago.
     */
    private function unreachable(string $address, string $path): bool
    {
        return Cache::has($this->cacheKey('unreachable', $address))
            || ($path !== '' && Cache::has($this->cacheKey('unreachable', $address, $path)));
    }

    /** Where the folder lives, as far as it is remembered. */
    private function knownRoot(string $address): ?array
    {
        $root = Cache::get($this->cacheKey('root', $address));

        return is_array($root) && isset($root['drive_id'], $root['item_id']) ? $root : null;
    }

    /**
     * Where the folder lives: remembered, or looked up from the address.
     *
     * @return array{drive_id: string, item_id: string, name: string, web_url: ?string}
     */
    private function root(string $address): array
    {
        if (($root = $this->knownRoot($address)) !== null) {
            return $root;
        }

        try {
            $item = $this->graph->get('/shares/' . self::shareId($address) . '/driveItem');
        } catch (GraphException $e) {
            // Graph's own 400: it does not recognise the address as something in SharePoint at
            // all. (A 400 of the sign-in is about the application and keeps its reason.)
            throw $e->status === 400 && $e->reason === GraphException::UNAVAILABLE
                ? new GraphException(GraphException::NOT_FOUND, $e->getMessage(), 400)
                : $e;
        }

        $driveId = $item['parentReference']['driveId'] ?? null;
        $itemId = $item['id'] ?? null;
        if (! self::isId($driveId) || ! self::isId($itemId)) {
            throw new GraphException(GraphException::UNAVAILABLE, 'Graph described the folder without usable ids.');
        }
        if (! isset($item['folder'])) {
            throw new GraphException(GraphException::NOT_A_FOLDER, 'The configured address is a file.');
        }

        $root = [
            'drive_id' => $driveId,
            'item_id' => $itemId,
            'name' => is_string($item['name'] ?? null) ? $item['name'] : '',
            'web_url' => self::https($item['webUrl'] ?? null),
        ];
        Cache::put($this->cacheKey('root', $address), $root, now()->addHours(self::ROOT_HOURS));

        return $root;
    }

    /** @param array{drive_id: string, item_id: string} $root */
    private function fetch(array $root, string $path): array
    {
        $folder = "/drives/{$root['drive_id']}/items/{$root['item_id']}";
        $children = $path === '' ? "{$folder}/children" : "{$folder}:/" . self::encodePath($path) . ':/children';

        try {
            $result = $this->graph->all($children, ['$top' => 999, '$select' => self::FIELDS]);
        } catch (GraphException $e) {
            // Graph's own 400 for a path below: a name it cannot address. That is about this
            // path ("not found"), not about reaching Microsoft.
            throw $path !== '' && $e->status === 400 && $e->reason === GraphException::UNAVAILABLE
                ? new GraphException(GraphException::NOT_FOUND, $e->getMessage(), 400)
                : $e;
        }

        $items = [];
        foreach ($result['items'] as $item) {
            if (is_string($item['name'] ?? null) && $item['name'] !== '') {
                $items[] = self::row($item);
            }
        }

        return ['format' => self::FORMAT, 'fetched_at' => now()->getTimestamp(), 'truncated' => $result['truncated'], 'items' => $items];
    }

    /** Only what the page shows (and the id a file is asked for by) is kept. */
    private static function row(array $item): array
    {
        $by = $item['lastModifiedBy']['user']['displayName'] ?? $item['lastModifiedBy']['application']['displayName'] ?? null;

        return [
            'id' => self::isId($item['id'] ?? null) ? $item['id'] : null,
            'name' => $item['name'],
            'folder' => isset($item['folder']),
            'size' => is_numeric($item['size'] ?? null) ? (int) $item['size'] : 0,
            'modified' => self::timestamp($item['lastModifiedDateTime'] ?? null),
            'modified_by' => is_string($by) && $by !== '' ? $by : null,
            'url' => self::https($item['webUrl'] ?? null),
        ];
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

    /**
     * Whether the bytes start the way a file of that type does: a page or a script that only
     * carries a harmless name is not sent on under that name's type.
     */
    private static function startsLike(string $type, string $body): bool
    {
        return match ($type) {
            'application/pdf' => str_contains(substr($body, 0, 1024), '%PDF-'),
            'image/png' => str_starts_with($body, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($body, "\xFF\xD8\xFF"),
            'image/gif' => str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a'),
            'image/webp' => str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP',
            default => false,
        };
    }

    /** Links are shown as they come from SharePoint, but only when they are ordinary https links. */
    private static function https(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
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

    private static function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function cacheKey(string $kind, string $address, string $path = ''): string
    {
        return "sharepoint-docs:{$kind}:" . sha1($address . "\n" . $path);
    }
}
