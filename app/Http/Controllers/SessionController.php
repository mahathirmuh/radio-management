<?php

namespace App\Http\Controllers;

use App\Services\SessionClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The clock of the signed-in session, for the page's script (sessionTimer in main/components/js): how long is left,
 * and "stay signed in". Both answer the seconds left; only the second counts as activity (EndExpiredSession).
 */
class SessionController extends Controller
{
    public function status(Request $request, SessionClock $clock): JsonResponse
    {
        return response()->json($clock->left($request->session()));
    }

    public function keepAlive(Request $request, SessionClock $clock): JsonResponse
    {
        return response()->json($clock->left($request->session()));
    }
}
