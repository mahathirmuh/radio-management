<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An endpoint whose action is a person's decision (approving or turning down a change of the workbook): only with a
 * personal token, never with the token of an API client. Who decided is then always somebody, as on the page.
 */
class PersonOnly
{
    public const REFUSED = 'This is a decision of a person: use a personal token (page API Tokens), not the token of an API client.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isApiClient()) {
            return response()->json(['message' => self::REFUSED], 403);
        }

        return $next($request);
    }
}
