<?php

namespace App\Http\Middleware;

use App\Services\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of the request (App\Services\Language). A page passes here twice: first among the global middleware,
 * where only the browser's choice is known (so that an address that is no page, and the maintenance page, speak it too),
 * then after the session has started, where the account's choice wins. The browser is then given the account's choice,
 * so that the sign-in page speaks it after signing out. "api": the language of the REST API.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, ?string $for = null): Response
    {
        if ($for === 'api') {
            Language::use(Language::forApi($request));

            return $next($request);
        }

        Language::use(Language::forRequest($request));
        $response = $next($request);

        // Chosen in another browser, or before this one had the cookie: this browser keeps it from now on.
        $chosen = $request->hasSession() ? Language::valid($request->user()?->locale) : null;
        if ($chosen !== null && $request->cookies->get(Language::COOKIE) !== $chosen) {
            Cookie::queue(Language::COOKIE, $chosen, Language::COOKIE_MINUTES);
        }

        return $response;
    }
}
