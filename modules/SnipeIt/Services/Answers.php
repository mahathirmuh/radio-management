<?php

namespace Modules\SnipeIt\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The answers of Snipe-IT, kept for a few minutes (services.snipeit.cache_minutes): paging back and forth, or opening the
 * page again, does not ask Snipe-IT again. The button "Refresh" asks at once ($fresh).
 */
final class Answers
{
    public function __construct(private readonly SnipeItClient $client)
    {
    }

    /** How long an answer is kept; 0 keeps none. */
    public static function seconds(): int
    {
        return max(0, (int) config('services.snipeit.cache_minutes', 5)) * 60;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     *
     * @throws SnipeItUnavailable
     */
    public function get(string $endpoint, array $query, bool $fresh = false): array
    {
        $key = 'snipe-it-answer:' . sha1($endpoint . '?' . http_build_query($query));
        if ($fresh) {
            Cache::forget($key);
        }
        if (self::seconds() === 0) {
            return $this->client->get($endpoint, $query);
        }

        return Cache::remember($key, self::seconds(), fn () => $this->client->get($endpoint, $query));
    }
}
