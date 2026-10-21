<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Api\Models\ApiToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The code is live before its migrations run: until the table of the tokens is there, the API says so (503) instead
 * of failing on a missing table.
 */
class ApiReady
{

    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiToken::ready()) {
            return response()->json(['message' => __('Api::api.not_ready')], 503);
        }

        return $next($request);
    }
}
