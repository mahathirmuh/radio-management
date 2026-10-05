<?php

namespace Modules\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserManagementMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}