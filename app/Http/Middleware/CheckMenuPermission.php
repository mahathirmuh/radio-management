<?php

namespace App\Http\Middleware;

use App\Models\UserManagement\Menu;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the menu/role/permission (RBAC) matrix automatically for any route
 * that is tied to a menu row, so individual controllers no longer need to call
 * guardRole()/hasMenuPermission() by hand.
 *
 * How a request is resolved:
 *   1. route name (e.g. "user-management.role-permission.store")
 *   2. -> menu key = route name without the trailing action ("user-management.role-permission")
 *   3. -> Menu whose `route` shares that key
 *   4. -> ACCESS check (menu_role) + CRUD check (menu_role_permission) by HTTP verb
 *
 * Policy: DEFAULT OPEN. Routes with no name, or not tied to any menu, pass
 * through untouched so modules can be onboarded gradually.
 */
class CheckMenuPermission
{
    /** Permission name => permissions.id (see PermissionsTableSeeder). */
    public const PERMISSION_IDS = [
        'Create' => 1,
        'Read'   => 2,
        'Update' => 3,
        'Delete' => 4,
    ];

    /** HTTP verb => required permission id for mutating requests. */
    private const VERB_PERMISSION = [
        'POST'   => 1, // Create
        'PUT'    => 3, // Update
        'PATCH'  => 3, // Update
        'DELETE' => 4, // Delete
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 'auth' already guarantees a user; stay defensive if this runs standalone.
        if (!$user) {
            return $next($request);
        }

        // Super admin (role id 1) bypasses every check.
        if ($user->roles->contains('id', 1)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return $next($request); // unnamed route -> not guarded
        }

        $menu = static::menuForRoute($routeName);
        if (!$menu) {
            return $next($request); // route not tied to a menu -> default open
        }

        // 1) ACCESS: role must have the menu granted (menu_role).
        abort_unless(
            $user->guardRole($menu->id),
            Response::HTTP_FORBIDDEN,
            'You do not have access to this menu.'
        );

        // 2) CRUD: the page shell (.index) needs only ACCESS; data operations
        //    (.list/.show + mutations) need the matching permission.
        $permissionId = static::requiredPermissionId($routeName, $request->method());
        if ($permissionId !== null) {
            abort_unless(
                $user->hasMenuPermission($menu->id, $permissionId),
                Response::HTTP_FORBIDDEN,
                'You are not allowed to perform this action.'
            );
        }

        return $next($request);
    }

    /**
     * Permission id a route requires, or null when only ACCESS is needed.
     */
    public static function requiredPermissionId(string $routeName, string $method): ?int
    {
        // Opening the listing page requires only ACCESS, so a menu can be entered;
        // loading its rows (.list/.show) and mutations still require CRUD perms.
        if (Str::endsWith($routeName, '.index')) {
            return null;
        }

        if (in_array($method, ['GET', 'HEAD'], true)) {
            return self::PERMISSION_IDS['Read'];
        }

        return self::VERB_PERMISSION[$method] ?? null;
    }

    /** Resolve the Menu that owns a given route name, or null. */
    public static function menuForRoute(string $routeName): ?Menu
    {
        return static::menuForKey(Str::beforeLast($routeName, '.'));
    }

    /** Resolve a Menu by its menu-key (route without the trailing action), or null. */
    public static function menuForKey(string $menuKey): ?Menu
    {
        $menuId = static::routeKeyMap()[$menuKey] ?? null;

        return $menuId ? Menu::find($menuId) : null;
    }

    /**
     * [menuKey => menuId] built from every menu that carries a route. Cached
     * because the menu structure rarely changes; the per-role permission checks
     * themselves are NOT cached, so grants/revocations take effect immediately.
     *
     * Clear with `Cache::forget('rbac_menu_route_key_map')` after adding menus.
     */
    private static function routeKeyMap(): array
    {
        return Cache::remember('rbac_menu_route_key_map', 3600, function () {
            return Menu::query()
                ->whereNotNull('route')
                ->get(['id', 'route'])
                ->mapWithKeys(fn (Menu $menu) => [Str::beforeLast($menu->route, '.') => $menu->id])
                ->all();
        });
    }
}
