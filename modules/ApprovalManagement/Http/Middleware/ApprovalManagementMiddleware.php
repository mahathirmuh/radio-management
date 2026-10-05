<?php

namespace Modules\ApprovalManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApprovalManagementMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}