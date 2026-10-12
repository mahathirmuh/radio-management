<?php

namespace Modules\SharePointDocs\Services;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Access to Microsoft Graph with the application's own identity (client credentials of the Entra
 * app in services.azure, the one that also does the Microsoft sign-in). It reads; the one thing it
 * writes is the content of a file (upload()), and only when a change has been approved.
 *
 * The token lives in this object only, never in the cache or a file: it can read every site
 * the application was given.
 */
class GraphClient
{
    private const LOGIN = 'https://login.microsoftonline.com/';

    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** No request is started with less than this many seconds left of the time allowed. */
    private const SHORTEST_ATTEMPT = 1.0;

    /** Seconds a request may take to make its connection (less when the time allowed is shorter). */
    private const CONNECT_LIMIT = 5;

    /** Seconds waited before the one second attempt at a busy Microsoft. */
    private const PAUSE = 0.4;

    private ?string $token = null;

    /** Unix time until which $token is used (a worker that stays up outlives a token). */
    private int $tokenUntil = 0;

    /** Time (see clock()) by which the reading in within() must be over; null outside one. */
    private ?float $deadline = null;

    /** Whether the time of the reading in within() was cut out of the time of a whole request that has more to do, and made much shorter than usual (see within()). */
    private bool $shared = false;

    public function configured(): bool
    {
        return filled(config('services.azure.tenant'))
            && filled(config('services.azure.client_id'))
            && filled(config('services.azure.client_secret'));
    }

    /**
     * Runs $reading, which may ask Microsoft any number of times (get(), all(), locate(),
     * fetch()), in at most $seconds altogether: the sign-in and every request share that time.
     * When it is used up nothing more is asked and the reading fails as "unavailable" (a file
     * that was arriving: "too slow"), instead of keeping a page, and a one-request-at-a-time
     * server, waiting. Inside another within() the time of the outer one counts.
     *
     * @param bool $shared the time is what was left of a request that has another source to read too, and is
     *     much shorter than the time this reading ordinarily gets: running out of it says nothing about
     *     Microsoft (see shared())
     */
    public function within(float $seconds, callable $reading, bool $shared = false): mixed
    {
        if ($this->deadline !== null) {
            return $reading();
        }

        $this->deadline = self::clock() + $seconds;
        $this->shared = $shared;
        try {
            return $reading();
        } finally {
            $this->deadline = null;
            $this->shared = false;
        }
    }

    /**
     * Whether the reading that is going on has a time cut out of a bigger request's and made much shorter than usual
     * (within() with $shared). A reading that ran out of such a time was not too slow for Microsoft's sake: it is not
     * held back for it.
     */
    public function shared(): bool
    {
        return $this->deadline !== null && $this->shared;
    }

    /** One resource, e.g. "/shares/{id}/driveItem". */
    public function get(string $path, array $query = []): array
    {
        return $this->read(self::GRAPH . $path, $query);
    }

    /**
     * Every item of a collection, following Graph's paging links.
     *
     * @return array{items: array<int, array>, truncated: bool} truncated: more pages existed than $maxPages
     */
    public function all(string $path, array $query = [], int $maxPages = 20): array
    {
        $items = [];
        $url = self::GRAPH . $path;

        for ($page = 0; $url !== null && $page < $maxPages; $page++) {
            // The paging link already carries the query of the first request.
            $body = $this->read($url, $page === 0 ? $query : []);
            if (! is_array($body['value'] ?? null)) {
                throw new GraphException(GraphException::UNAVAILABLE, 'Graph answered a collection request without a "value" list.');
            }
            foreach ($body['value'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $url = $this->nextLink($body);
        }

        return ['items' => $items, 'truncated' => $url !== null];
    }

    /**
     * Where the bytes of a file are, for e.g. "/drives/{id}/items/{id}/content". Graph does not
     * send the file itself: it answers with a one-time address at SharePoint ("address", to be
     * read with fetch()). Should it send the bytes after all, they are in "body".
     *
     * Graph's redirect is not followed (no request with the token follows one, see http()): the
     * token stays with this one request.
     *
     * @return array{address: ?string, body: ?string}
     */
    public function locate(string $path): array
    {
        $token = $this->token();
        $pointer = $this->send(fn (PendingRequest $http) => $http->withToken($token)->get(self::GRAPH . $path));

        if ($pointer->redirect()) {
            $address = $pointer->header('Location');
            if (! str_starts_with($address, 'https://')) {
                throw new GraphException(GraphException::UNAVAILABLE, 'Graph pointed to a download address that is not https.');
            }

            return ['address' => $address, 'body' => null];
        }
        if (! $pointer->successful()) {
            throw $this->refusal($pointer);
        }

        return ['address' => null, 'body' => $pointer->body()];
    }

    /**
     * The bytes at a one-time address from locate(), at most $maxBytes of them.
     *
     * Asked WITHOUT the token (the address carries its own permission) and over https only. The
     * transfer may take sharepoint-docs.preview_time_limit seconds, or what is left of within():
     * more than one ordinary request, because it is the file itself. cURL is told to give it up
     * as soon as more than $maxBytes are announced or have arrived, or that time is over (the
     * time is for all of it, also when the address sends the application on to another one).
     */
    public function fetch(string $address, int $maxBytes): string
    {
        $atMost = max(1, (float) config('sharepoint-docs.preview_time_limit', 25));
        $left = min($atMost, $this->timeLeft());
        $until = self::clock() + $left;
        $tooLarge = false;
        $late = false;
        $timedOut = false;
        // Whether bytes of the file itself were coming. Only a 2xx answer is the file (an error
        // page or a redirect is not), and nothing follows one: no second attempt, no other address.
        $answering = false;
        $arriving = false;

        $options = [
            'allow_redirects' => ['max' => 2, 'protocols' => ['https']],
            // The answer the bytes that follow belong to.
            'on_headers' => function (ResponseInterface $answer) use (&$answering): void {
                $answering = $answer->getStatusCode() >= 200 && $answer->getStatusCode() < 300;
            },
            'curl' => [
                CURLOPT_NOPROGRESS => false,
                // Called by cURL while a transfer runs: with every piece that arrives, and about
                // once a second when nothing does. Returning non-zero stops the transfer at once
                // (throwing would not: the rest would still be read, and thrown away).
                CURLOPT_XFERINFOFUNCTION => function ($handle, $expected, $received) use ($maxBytes, $until, &$tooLarge, &$late, &$answering, &$arriving): int {
                    $arriving = $arriving || ($answering && $received > 0);
                    $tooLarge = $tooLarge || $received > $maxBytes || $expected > $maxBytes;
                    $late = $late || self::clock() > $until;

                    return $tooLarge || $late ? 1 : 0;
                },
            ],
        ];

        try {
            $response = $this->send(fn (PendingRequest $http) => $http->accept('*/*')->withOptions($options)->get($address), $atMost, $timedOut);
        } catch (GraphException $e) {
            throw match (true) {
                $tooLarge => new GraphException(GraphException::TOO_LARGE, 'The file is larger than what is shown inside the application.'),
                // The time ran out while bytes of the file were coming: a slow line or a big
                // file, not an outage. Anything else that goes wrong on the way stays what it is.
                $arriving && ($late || $timedOut) => new GraphException(GraphException::TOO_SLOW, 'The file did not arrive within the time allowed. ' . $e->getMessage()),
                $late => new GraphException(GraphException::UNAVAILABLE, 'Nothing of the file arrived within the time allowed.', 0, $left < $atMost),
                default => $e,
            };
        }

        if (! $response->successful()) {
            // The download address does not speak for Graph: a file that is gone is gone,
            // anything else it answers is a passing trouble.
            throw new GraphException(
                $response->status() === 404 ? GraphException::NOT_FOUND : GraphException::UNAVAILABLE,
                "The download address answered {$response->status()}.",
                $response->status()
            );
        }
        if (strlen($response->body()) > $maxBytes) {
            throw new GraphException(GraphException::TOO_LARGE, 'The file is larger than what is shown inside the application.');
        }

        return $response->body();
    }

    /**
     * Puts $bytes in the place of the content of a workbook, for e.g. "/drives/{id}/items/{id}/content".
     *
     * $ifMatch is the version (eTag) of the file that $bytes were made from: SharePoint saves them only when that is
     * still the version it has (else CHANGED, and nothing is written), so that a file somebody saved in between is
     * never written over. A file that somebody has open in Excel cannot be written (LOCKED). Every save is a new
     * version of the file in SharePoint, which can be restored there.
     *
     * (A busy Microsoft is asked a second time, like any request, and that second request can be refused as CHANGED
     * when the first one was saved after all: whoever calls this checks the file before taking that for a failure.)
     *
     * @return array the file as Graph describes it after the save
     */
    public function upload(string $path, string $bytes, ?string $ifMatch = null): array
    {
        $token = $this->token();
        $atMost = max(1, (float) config('sharepoint-docs.preview_time_limit', 25));
        $headers = $ifMatch !== null && $ifMatch !== '' ? ['If-Match' => $ifMatch] : [];

        $response = $this->send(fn (PendingRequest $http) => $http->withToken($token)->withHeaders($headers)
            ->withBody($bytes, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->put(self::GRAPH . $path . '?@microsoft.graph.conflictBehavior=replace'), $atMost);

        if (! $response->successful()) {
            throw $this->refusal($response);
        }
        $body = $response->json();
        if (! is_array($body)) {
            throw new GraphException(GraphException::UNAVAILABLE, "An answer with status {$response->status()} whose body is not JSON, so not Graph's.");
        }

        return $body;
    }

    /** The paging link is followed only when it stays on Graph: the token goes nowhere else. */
    private function nextLink(array $body): ?string
    {
        // Not data_get(): the key itself contains a dot.
        $next = $body['@odata.nextLink'] ?? null;
        if ($next === null) {
            return null;
        }
        if (! is_string($next) || ! str_starts_with($next, self::GRAPH . '/')) {
            throw new GraphException(GraphException::UNAVAILABLE, 'Graph returned a paging link outside ' . self::GRAPH . '.');
        }

        return $next;
    }

    private function read(string $url, array $query): array
    {
        $token = $this->token();
        // An empty query must not be passed on: it would replace the one a paging link carries.
        $response = $this->send(fn (PendingRequest $http) => $query === [] ? $http->withToken($token)->get($url) : $http->withToken($token)->get($url, $query));

        if ($response->successful()) {
            $body = $response->json();
            if (! is_array($body)) {
                // Something in between answered (a proxy's page, a cut-off body): not Graph's
                // word on anything, so its status is not passed on as Graph's either.
                throw new GraphException(GraphException::UNAVAILABLE, "An answer with status {$response->status()} whose body is not JSON, so not Graph's.");
            }

            return $body;
        }

        throw $this->refusal($response);
    }

    /**
     * What an answer of Graph that is not a 2xx means. Graph says what is wrong in its own form
     * ("error.code"). An answer without it comes from something in between (a web filter's block
     * page, a proxy) and is no word on rights or folders: Microsoft was not reached.
     */
    private function refusal(Response $response): GraphException
    {
        $code = $response->json('error.code');
        $detail = sprintf('Graph answered %d %s: %s', $response->status(), self::text($code), self::text($response->json('error.message')));
        if (! is_string($code) || $code === '') {
            return new GraphException(GraphException::UNAVAILABLE, "An answer with status {$response->status()} that is not in Graph's form.");
        }

        return new GraphException(match ($response->status()) {
            401 => GraphException::CREDENTIALS,
            403 => GraphException::FORBIDDEN,
            404 => GraphException::NOT_FOUND,
            412 => GraphException::CHANGED,
            423 => GraphException::LOCKED,
            default => GraphException::UNAVAILABLE,
        }, $detail, $response->status());
    }

    private function token(): string
    {
        if ($this->token !== null && now()->getTimestamp() < $this->tokenUntil) {
            return $this->token;
        }
        if (! $this->configured()) {
            throw new GraphException(GraphException::NOT_CONFIGURED, 'services.azure tenant / client_id / client_secret are not all set.');
        }

        $response = $this->send(fn (PendingRequest $http) => $http->asForm()->post(
            self::LOGIN . rawurlencode((string) config('services.azure.tenant')) . '/oauth2/v2.0/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
            ]
        ));

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            // error_description names the application and the failing check, never the secret.
            $detail = sprintf('Token request answered %d %s: %s', $response->status(), self::text($response->json('error')), self::text($response->json('error_description')));
            // Microsoft refusing the application is a 4xx that names the error. A busy Microsoft,
            // or an answer in another form (a 2xx without a token, a filter's page: not
            // Microsoft's answer at all), says nothing about the credentials.
            $refused = $response->clientError() && $response->status() !== 429 && is_string($response->json('error'));
            // The status is passed on only as Microsoft's: "unavailable" with a status reads as an answer of Graph.
            throw new GraphException($refused ? GraphException::CREDENTIALS : GraphException::UNAVAILABLE, $detail, $refused ? $response->status() : 0);
        }

        // Microsoft says how long the token is good for (about an hour); it is dropped a little earlier.
        $lifetime = is_numeric($response->json('expires_in')) ? (int) $response->json('expires_in') : 300;
        $this->tokenUntil = now()->getTimestamp() + max(0, $lifetime - 120);

        return $this->token = $token;
    }

    /**
     * One request, asked a second time when Microsoft is busy (429 / 502 / 503 / 504) and there
     * is time for it; every other answer comes straight back. Whatever goes wrong on the way
     * (no connection, a timeout, a reset, a refusing proxy, a bad certificate) is "unavailable".
     *
     * @param float|null $atMost seconds this request may take; null: sharepoint-docs.timeout
     * @param bool $timedOut set when what went wrong on the way was the request's time running out
     */
    private function send(callable $request, ?float $atMost = null, bool &$timedOut = false): Response
    {
        $short = ['connect' => false, 'total' => false];
        try {
            $response = $request($this->http($atMost, $short));
            if (in_array($response->status(), [429, 502, 503, 504], true) && $this->timeLeft() >= self::PAUSE + self::SHORTEST_ATTEMPT) {
                Sleep::for((int) (self::PAUSE * 1000))->milliseconds();
                $response = $request($this->http($atMost, $short));
            }

            return $response;
        } catch (ConnectionException|TransferException $e) {
            // Laravel turns only some transport failures into ConnectionException; the rest arrive as Guzzle's own.
            $limit = self::limitThatRanOut($e);
            $timedOut = $limit !== null;

            throw new GraphException(
                GraphException::UNAVAILABLE,
                'The request to Microsoft failed on the way: ' . Str::limit(self::withoutQuery($e->getMessage()), 300),
                0,
                // Giving up when a limit that the time allowed had made shorter ran out says little about
                // Microsoft; one that ran out after all the time it ordinarily gets says as much as ever.
                $limit !== null && $short[$limit]
            );
        }
    }

    /**
     * A request that may take $atMost seconds (sharepoint-docs.timeout when null), and never
     * more than is left of the time allowed. It follows no redirect: the token goes only where
     * it was sent, and the time is for one answer.
     *
     * @param array{connect: bool, total: bool} $short which of the two limits, the connection's and the
     *     whole request's, the time allowed made shorter than any request would have it
     */
    private function http(?float $atMost = null, array &$short = []): PendingRequest
    {
        $left = $this->timeLeft();
        if ($left < self::SHORTEST_ATTEMPT) {
            throw new GraphException(GraphException::UNAVAILABLE, 'The time allowed for reading from Microsoft is used up.', 0, true);
        }

        $own = $atMost ?? max(1, (int) config('sharepoint-docs.timeout', 10));
        $timeout = min($own, $left);
        // Shortened is shorter than the request would have it without the time allowed: for the connection that is
        // the connect limit or, when the request as a whole may take less than that, the whole of it.
        $short = ['connect' => $timeout < min(self::CONNECT_LIMIT, $own), 'total' => $timeout < $own];
        $pending = Http::acceptJson()->withoutRedirecting()->connectTimeout(min(self::CONNECT_LIMIT, $timeout))->timeout($timeout);

        // The same outbound proxy as the Microsoft sign-in, when one is configured.
        return filled(config('services.azure.proxy')) ? $pending->withOptions(['proxy' => config('services.azure.proxy')]) : $pending;
    }

    /** Seconds left of the time that within() allows; unlimited (INF) outside one. */
    public function timeLeft(): float
    {
        return $this->deadline === null ? INF : $this->deadline - self::clock();
    }

    /** The time in seconds, the way the rest of the application reads it (a test can let time pass). */
    private static function clock(): float
    {
        return now()->getPreciseTimestamp() / 1_000_000;
    }

    /**
     * Which limit of a request ran out, when one did: cURL's error 28, which Guzzle's exception
     * knows. "connect" when no connection had been made yet (cURL's connect time is 0 then), else "total".
     * (Measured with the cURL of this PHP on Windows against a socket that took the connection and never
     * answered the TLS handshake: the connect limit ran out there too, with connect_time 0; the time
     * before the transfer, pretransfer_time, is not 0 then, so it cannot tell the two apart.)
     *
     * @return 'connect'|'total'|null
     */
    private static function limitThatRanOut(Throwable $e): ?string
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if (method_exists($cause, 'getHandlerContext')) {
                $context = $cause->getHandlerContext();
                if (($context['errno'] ?? null) === CURLE_OPERATION_TIMEDOUT) {
                    return (float) ($context['connect_time'] ?? 1.0) > 0 ? 'total' : 'connect';
                }
            }
        }

        return null;
    }

    /** What the HTTP client says went wrong, without the query of the address it names: a one-time address carries its permission there. */
    private static function withoutQuery(string $message): string
    {
        return (string) preg_replace('/\?\S*/', '?…', $message);
    }

    /** One line of what Microsoft said, for the log. */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? Str::limit(trim((string) strtok((string) $value, "\r\n")), 300) : '';
    }
}
