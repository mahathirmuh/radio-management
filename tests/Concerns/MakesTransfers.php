<?php

namespace Tests\Concerns;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;

/**
 * What cURL does on the way, for the tests in which Http::fake() plays Microsoft: answers to
 * hand to the fake that behave like a download being watched, a request running out of time, a
 * connection being cut, or time passing before the answer comes.
 */
trait MakesTransfers
{
    /**
     * What cURL does during a download: it reports the status of the answer (Guzzle's
     * "on_headers"), then calls the function the application gave it to watch the transfer with
     * (handle, bytes announced, bytes received ...), and gives the transfer up with error 42
     * when the answer is not zero. Otherwise the pretend download goes on with $otherwise.
     * $status 0: nothing has come back so far, not even the status.
     */
    protected static function transfer(int $announced, int $received, callable $otherwise, int $status = 200): callable
    {
        return function (Request $request, array $options) use ($announced, $received, $otherwise, $status) {
            if ($status !== 0) {
                $options['on_headers'](new Response($status));
            }
            $stop = $options['curl'][CURLOPT_XFERINFOFUNCTION](null, $announced, $received, 0, 0);

            return $stop !== 0
                ? Create::rejectionFor(new RequestException('cURL error 42: Callback aborted', $request->toPsrRequest(), null, null, ['errno' => 42]))
                : $otherwise($request, $options);
        };
    }

    /**
     * cURL giving a request up because its time is over (error 28), the way Guzzle reports that: with
     * the connect time of curl_getinfo() in its context, 0 when no connection had been made yet (the limit
     * for connecting ran out), else the time the connection took (the limit for the whole request ran out).
     * A real cURL says so: measured against a socket that never answers, against an address that is never routed, and
     * (on Windows) against a socket that took the connection and never answered the TLS handshake, where the limit
     * for connecting runs out too and connect_time is 0 as well, while pretransfer_time is not.
     */
    protected static function timeout(bool $connecting = false): callable
    {
        return fn (Request $request) => Create::rejectionFor(new ConnectException(
            ($connecting
                ? 'cURL error 28: Connection timed out after 5001 milliseconds'
                : 'cURL error 28: Operation timed out after 24000 milliseconds with 100000 out of 500000 bytes received')
                . " (see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for {$request->url()}",
            $request->toPsrRequest(),
            null,
            ['errno' => 28, 'connect_time' => $connecting ? 0.0 : 0.04]
        ));
    }

    /** A failure on the way that Laravel leaves as Guzzle's exception (cURL 56, 18, 60 ...), unlike Http::failedConnection(). */
    protected static function connectionReset(): callable
    {
        return fn (Request $request) => Create::rejectionFor(
            new RequestException('cURL error 56: Recv failure: Connection was reset by the proxy', $request->toPsrRequest(), null, null, ['errno' => 56])
        );
    }

    /** $answer, given only after $seconds have passed: the clock the application reads is moved on. */
    protected function after(float $seconds, callable $answer): callable
    {
        return function (Request $request, array $options) use ($seconds, $answer) {
            $this->travel((int) round($seconds * 1000))->milliseconds();

            return $answer($request, $options);
        };
    }
}
