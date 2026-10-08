<?php

namespace Modules\SharePointDocs\Http\Middleware;

use App\Http\Middleware\CheckMenuPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `permission` middleware lets a route through when no menu row belongs to it (the base
 * application's "default open", for modules that are not in the menu yet). These pages show
 * documents of another system, so here it is the other way round: without a menu row to grant
 * them by, they are closed to everyone but Super Admin.
 */
class RequireMenu
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();

        abort_unless(
            $request->user()?->roles->contains('id', 1) || CheckMenuPermission::menuForRoute($routeName) !== null,
            Response::HTTP_FORBIDDEN,
            'This page has not been added to the menu yet.'
        );

        return $next($request);
    }
}
