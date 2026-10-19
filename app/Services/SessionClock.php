<?php

namespace App\Services;

use Illuminate\Contracts\Session\Session;

/**
 * The two clocks of a signed-in session (OWASP Session Management; NIST SP 800-63B-4, AAL2): it ends after
 * config('session.idle_timeout') minutes without activity, and config('session.absolute_timeout') minutes after
 * signing in, whatever is done meanwhile. They are kept in the session itself, so the server decides: a page only
 * asks how long is left (SessionController) to warn in time.
 */
class SessionClock
{
    /** When the person signed in, and when they last did something (Unix seconds). */
    public const SIGNED_IN = 'session_clock.signed_in_at';

    public const LAST_SEEN = 'session_clock.last_seen_at';

    public function idleMinutes(): int
    {
        return max(1, (int) config('session.idle_timeout', 30));
    }

    public function absoluteMinutes(): int
    {
        return max(1, (int) config('session.absolute_timeout', 480));
    }

    /** Signing in starts both clocks. */
    public function start(Session $session): void
    {
        $now = now()->getTimestamp();
        $session->put([self::SIGNED_IN => $now, self::LAST_SEEN => $now]);
    }

    /**
     * Why the session is over, "absolute" or "idle", or null while it runs. A session without clocks (one opened
     * before they were kept) gets them now.
     */
    public function expired(Session $session): ?string
    {
        $now = now()->getTimestamp();
        foreach ([self::SIGNED_IN, self::LAST_SEEN] as $key) {
            if (! is_int($session->get($key))) {
                $session->put($key, $now);
            }
        }

        return match (true) {
            $now - $session->get(self::SIGNED_IN) >= $this->absoluteMinutes() * 60 => 'absolute',
            $now - $session->get(self::LAST_SEEN) >= $this->idleMinutes() * 60 => 'idle',
            default => null,
        };
    }

    /** The person did something: the idle clock starts again. */
    public function touch(Session $session): void
    {
        $session->put(self::LAST_SEEN, now()->getTimestamp());
    }

    /**
     * The seconds left before each timeout.
     *
     * @return array{idle_left: int, absolute_left: int}
     */
    public function left(Session $session): array
    {
        $now = now()->getTimestamp();
        $since = fn (string $key) => $now - (is_int($session->get($key)) ? $session->get($key) : $now);

        return [
            'idle_left' => max(0, $this->idleMinutes() * 60 - $since(self::LAST_SEEN)),
            'absolute_left' => max(0, $this->absoluteMinutes() * 60 - $since(self::SIGNED_IN)),
        ];
    }
}
