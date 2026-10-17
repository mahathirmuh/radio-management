<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Account
 */
class MeController extends ApiController
{
    /**
     * The account and the token of this request
     *
     * Use it to check a token: who it acts as, what it may do, and until when.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return response()->json(['data' => [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                /** "client" for the account of an application (page API Tokens), "person" for a person's own token. */
                'kind' => $user->isApiClient() ? 'client' : 'person',
                'email' => $user->isApiClient() ? null : $user->email,
                'roles' => $user->roles->pluck('name')->values(),
            ],
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                /** "read", or "read" and "write". */
                'abilities' => array_values((array) $token->abilities),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
            ],
        ]]);
    }
}
