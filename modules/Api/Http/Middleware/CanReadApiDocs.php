<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The documentation of the API (/docs/api) is for whoever may hold a token: an account with access to the menu "API
 * Tokens" (Super Admin always). It shows no data, but it does show how to change it.
 */
class CanReadApiDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('viewApiDocs'), 403);

        return $next($request);
    }
}
