<?php

namespace App\Http\Middleware;

use App\Helpers\LogApps;
use Closure;
use App\Models\Log\LogActivity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class LogActivityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        if (strpos($request->fullUrl(), 'logout')) {
            return $response;
        } else {
            if ($request->method() != "GET") {
                $user = 'guest';
                if (Auth::check()) {
                    $user = Auth::user()->id;
                }
                try {
                    LogApps::activityLog($request, $user);
                } catch (\Throwable $e) {
                    // The request itself already succeeded; a failed log write must not undo that.
                    report($e);
                }
            }
            return $response;
        }
    }
}
