<?php

namespace Modules\SnipeIt\Services;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Snipe-IT's REST API (v1), read only: nothing here sends anything but GET, by design. The token in .env may be one that
 * can change everything there; this application only ever asks.
 *
 * The address and the token come from config('services.snipeit'). The token never leaves the server: not in a page, not
 * in an answer of this application, not in the log.
 */
final class SnipeItClient
{
    private const CONNECT_TIMEOUT = 5;

    private const TIMEOUT = 20;

    /**
     * Snipe-IT could not be reached a moment ago: for a minute it is not asked again (every page would wait for the time
     * limit first), unless somebody presses "Refresh" (retry()).
     */
    public const DOWN_KEY = 'snipe-it-unreachable';

    private const DOWN_SECONDS = 60;

    public static function configured(): bool
    {
        return filled(config('services.snipeit.url')) && filled(config('services.snipeit.token'));
    }

    /** Snipe-IT's own pages ("http://host:8110"), from the address of the API ("http://host:8110/api/v1"); null when that is not one. */
    public static function webUrl(): ?string
    {
        $api = rtrim((string) config('services.snipeit.url'), '/');
        if (! Str::endsWith($api, '/api/v1') || ! preg_match('#^https?://#i', $api)) {
            return null;
        }

        return Str::beforeLast($api, '/api/v1');
    }

    /**
     * What the API says to one question: $endpoint ("hardware", "reports/activity") with $query, as an array.
     *
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     *
     * @throws SnipeItUnavailable when Snipe-IT is not set up, cannot be reached, or answers with an error
     */
    public function get(string $endpoint, array $query = []): array
    {
        $this->mayAsk();

        try {
            $response = $this->prepare(Http::createPendingRequest())->get($this->address($endpoint), $query);
        } catch (ConnectionException|TransferException) {
            Log::warning('Snipe-IT could not be reached.', ['endpoint' => $endpoint]);
            Cache::put(self::DOWN_KEY, true, self::DOWN_SECONDS);
            throw new SnipeItUnavailable(SnipeItUnavailable::UNREACHABLE);
        }

        return $this->read($response, $endpoint);
    }

    /** Asks Snipe-IT again at once, even within the minute after it could not be reached. */
    public static function retry(): void
    {
        Cache::forget(self::DOWN_KEY);
    }

    /** @throws SnipeItUnavailable when Snipe-IT is not set up, or was not reached a moment ago */
    private function mayAsk(): void
    {
        if (! self::configured()) {
            throw new SnipeItUnavailable(SnipeItUnavailable::NOT_CONFIGURED);
        }
        if (Cache::has(self::DOWN_KEY)) {
            throw new SnipeItUnavailable(SnipeItUnavailable::UNREACHABLE);
        }
    }

    /**
     * Several questions at once (they go out together): [key => [endpoint, query]]. An answer that did not come, or came
     * as an error, is null; Snipe-IT not set up at all throws.
     *
     * @param  array<string, array{0: string, 1?: array<string, scalar>}>  $questions
     * @return array<string, array<string, mixed>|null>
     *
     * @throws SnipeItUnavailable when Snipe-IT is not set up, or was not reached a moment ago
     */
    public function many(array $questions): array
    {
        $this->mayAsk();

        $responses = Http::pool(function (Pool $pool) use ($questions) {
            $requests = [];
            foreach ($questions as $key => $question) {
                $requests[] = $this->prepare($pool->as((string) $key))->get($this->address($question[0]), $question[1] ?? []);
            }

            return $requests;
        });

        $answers = [];
        $reached = false;
        foreach ($questions as $key => $question) {
            $response = $responses[$key] ?? null;
            $reached = $reached || $response instanceof Response;
            try {
                $answers[$key] = $response instanceof Response ? $this->read($response, $question[0]) : null;
            } catch (SnipeItUnavailable) {
                $answers[$key] = null;
            }
        }
        if (! $reached && $questions !== []) {
            Log::warning('Snipe-IT could not be reached.', ['endpoints' => count($questions)]);
            Cache::put(self::DOWN_KEY, true, self::DOWN_SECONDS);
            throw new SnipeItUnavailable(SnipeItUnavailable::UNREACHABLE);
        }

        return $answers;
    }

    private function prepare(PendingRequest $request): PendingRequest
    {
        return $request->withToken((string) config('services.snipeit.token'))
            ->acceptJson()
            ->withoutRedirecting()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);
    }

    /** Only the endpoints the lists name (Lists): letters and slashes, nothing a request could put in. */
    private function address(string $endpoint): string
    {
        if (! preg_match('#^[a-z]+(/[a-z]+)*$#', $endpoint)) {
            throw new InvalidArgumentException("Not an endpoint of Snipe-IT: {$endpoint}");
        }

        return rtrim((string) config('services.snipeit.url'), '/') . '/' . $endpoint;
    }

    /**
     * The body of an answer. Snipe-IT says no with a status (401 for a wrong token), or with 200 and "status": "error".
     *
     * @return array<string, mixed>
     */
    private function read(Response $response, string $endpoint): array
    {
        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || ($body['status'] ?? null) === 'error') {
            Log::warning('Snipe-IT answered with an error.', ['endpoint' => $endpoint, 'status' => $response->status()]);
            throw new SnipeItUnavailable(
                in_array($response->status(), [401, 403], true) ? SnipeItUnavailable::REFUSED : SnipeItUnavailable::FAILED,
                $response->status()
            );
        }

        return $body;
    }
}
