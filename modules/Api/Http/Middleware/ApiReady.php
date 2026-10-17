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
    public const NOT_READY = 'The API is not switched on yet: its migration has not been run on this database.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! ApiToken::ready()) {
            return response()->json(['message' => self::NOT_READY], 503);
        }

        return $next($request);
    }
}
