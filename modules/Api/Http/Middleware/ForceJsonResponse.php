<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Everything under /api/v1 answers in JSON, errors included, whatever the caller put in its Accept header (a script
 * that sends none would otherwise get the login page for a missing token, or an HTML error page).
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
