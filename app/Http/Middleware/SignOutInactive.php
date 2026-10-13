<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * An account that is set to inactive in User Management is refused at sign-in (LoginController). This ends the
 * session it may still have open: on its next request it is signed out and sent to the login page.
 */
class SignOutInactive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'This account is inactive.'], 401)
                : redirect()->route('login');
        }

        return $next($request);
    }
}
