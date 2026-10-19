<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditLogger;
use App\Services\SessionClock;
use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a signed-in session that has been idle too long, or has reached its maximum length (SessionClock), on the
 * server: the page is sent to the login page, which says why, and a request of a page's script is answered 401 with
 * "session_ended", for the script to go there too.
 */
class EndExpiredSession
{
    /** What a page asks by itself (the bell, the session's own clock): not something the person did. */
    private const BACKGROUND = ['session.status', 'notifications.summary'];

    public function __construct(private readonly SessionClock $clock)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // The guard of the session (whatever guard a route of the API may have made the default).
        $guard = Auth::guard('web');
        if (! $guard->check()) {
            return $next($request);
        }
        $session = $request->session();
        // No "remember me" is offered: such a cookie (one from before) does not bring an ended session back.
        $reason = $guard->viaRemember() ? 'ended' : $this->clock->expired($session);
        if ($reason !== null) {
            return $this->end($request, $guard, $reason);
        }
        if (! in_array($request->route()?->getName(), self::BACKGROUND, true)) {
            $this->clock->touch($session);
        }

        return $next($request);
    }

    private function end(Request $request, StatefulGuard $guard, string $reason): Response
    {
        $why = match ($reason) {
            'idle' => 'You were inactive for ' . $this->clock->idleMinutes() . ' minutes.',
            'absolute' => 'A session lasts at most ' . $this->duration($this->clock->absoluteMinutes()) . '.',
            default => 'Your session has ended.',
        };
        app(AuditLogger::class)->log(AuditLogger::SESSION_EXPIRED, new: ['Reason' => $why], user: $guard->user());
        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // Shown on the login page, where both a page and a page's script end up.
        Alert::info('Session expired', $why . ' Please sign in again.')->persistent(true);

        return $request->expectsJson()
            ? response()->json(['message' => 'Session expired.', 'session_ended' => true, 'reason' => $reason], 401)
            : redirect()->route('login');
    }

    private function duration(int $minutes): string
    {
        return $minutes % 60 === 0
            ? ($minutes / 60) . ' ' . ($minutes === 60 ? 'hour' : 'hours')
            : $minutes . ' minutes';
    }
}
