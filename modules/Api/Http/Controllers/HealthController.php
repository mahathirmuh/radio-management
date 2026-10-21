<?php

namespace Modules\Api\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Api\Models\ApiToken;
use Modules\Api\Services\Health;
use Throwable;

/**
 * Whether the application is up, for whatever watches it: /up (Docker's health check: the application answers, nothing
 * else is asked) and /api/v1/health (the database and the storage; with a token, everything it depends on).
 */
class HealthController extends Controller
{
    /**
     * The application answers. No database, no session, no token: for the health check of a container, which restarts it
     * when this fails (a restart does not bring a database back: that is /api/v1/health).
     */
    public function up(): JsonResponse
    {
        return response()->json(['status' => 'up'])->header('Cache-Control', 'no-store');
    }

    /**
     * Health of the application
     *
     * Without a token: whether it works (its database and its storage), with its version; 503 when it does not. With a
     * token (any token of the API): every check, each with `status` (ok, warn, fail, skipped: not set up here), `ms`, and
     * when it is not ok a `reason` and a `message`: the database, the storage, the cache, the migrations not run yet,
     * Snipe-IT, Microsoft (sign-in and SharePoint), Active Directory, the HR database. `status` of the whole is then
     * "degraded" when something it depends on fails or needs a look. Nothing in it names a server or an error of the server.
     *
     * @unauthenticated
     */
    public function show(Request $request, Health $health): JsonResponse
    {
        $detail = false;
        if ($request->bearerToken() !== null) {
            $account = $this->account();
            // A token that is not one (wrong, revoked, expired, an inactive account): said so. A token that cannot be looked
            // at because the database is down: the answer without the details, which says that.
            if ($account === null) {
                return response()->json(['message' => __('Api::api.unauthenticated')], 401);
            }
            $detail = $account !== false;
        }

        $report = $health->report($detail);

        return response()->json(['data' => $report], $report['status'] === 'down' ? 503 : 200)->header('Cache-Control', 'no-store');
    }

    /**
     * The account of the token of this request: a person or a client with an API token, active. Null when the token is
     * no such one; false when it cannot be looked at (the database does not answer).
     */
    private function account(): mixed
    {
        try {
            $user = Auth::guard('sanctum')->user();
        } catch (Throwable) {
            return false;
        }

        return $user !== null && $user->currentAccessToken() instanceof ApiToken && $user->isActive() ? $user : null;
    }
}
