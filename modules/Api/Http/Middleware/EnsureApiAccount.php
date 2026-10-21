<?php

namespace Modules\Api\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Api\Models\ApiToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * After Sanctum has found the token (a deleted account has none, an expired token is refused there): the account must
 * still be active. Setting a person or an API client to inactive stops every one of their tokens at once.
 */
class EnsureApiAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->currentAccessToken() instanceof ApiToken) {
            return response()->json(['message' => __('Api::api.unauthenticated')], 401);
        }
        if (! $user->isActive()) {
            return response()->json(['message' => __('Api::api.inactive')], 401);
        }

        return $next($request);
    }
}
