<?php

namespace App\Listeners;

use App\Services\SessionClock;
use Illuminate\Auth\Events\Login;

/** Signing in (password, directory or Microsoft) starts the clocks of the session (EndExpiredSession). */
class StartSessionClock
{
    public function __construct(private readonly SessionClock $clock)
    {
    }

    public function handle(Login $event): void
    {
        // Only a sign-in of the web guard has a session (the API's tokens have none).
        if ($event->guard === 'web' && app()->bound('session.store')) {
            $this->clock->start(app('session.store'));
        }
    }
}
