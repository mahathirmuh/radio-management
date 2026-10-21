<?php

namespace Modules\Api\Http\Middleware;

use App\Http\Middleware\CheckMenuPermission;
use Closure;
use Illuminate\Http\Request;
use Modules\Api\Models\ApiToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who may do what through the API is what Role & Permission says for the pages: an endpoint belongs to the menu of
 * its page, and needs Read (GET), Create (POST), Update (PUT, PATCH) or Delete (DELETE) on it; an endpoint whose
 * action is a change of another kind names its permission (archiving a radio is an Update).
 *
 * Unlike the pages ("default open"), an endpoint whose menu is not in the database is closed, to everybody but Super
 * Admin. On top of the role, the token decides: a read-only token only reads.
 *
 *   ->middleware(ApiAccess::to('radio-management.radio'))            // by the method
 *   ->middleware(ApiAccess::to('radio-management.radio', 'Update'))  // named
 */
class ApiAccess
{
    private const BY_METHOD = [
        'GET' => 'Read',
        'HEAD' => 'Read',
        'POST' => 'Create',
        'PUT' => 'Update',
        'PATCH' => 'Update',
        'DELETE' => 'Delete',
    ];

    public static function to(string $menuKey, ?string $permission = null): string
    {
        return self::class . ':' . $menuKey . ($permission === null ? '' : ',' . $permission);
    }

    public function handle(Request $request, Closure $next, string $menuKey, ?string $permission = null): Response
    {
        $user = $request->user();
        $permission ??= self::BY_METHOD[$request->method()] ?? 'Update';
        abort_unless(isset(CheckMenuPermission::PERMISSION_IDS[$permission]), 500, 'Unknown permission ' . $permission);

        $token = $user->currentAccessToken();
        if ($permission !== 'Read' && ! ($token instanceof ApiToken && $token->writes())) {
            return $this->refuse(__('Api::api.read_only'));
        }

        // Super Admin (role id 1) passes every check, as on the pages.
        if ($user->roles->contains('id', 1)) {
            return $next($request);
        }

        $menu = CheckMenuPermission::menuForKey($menuKey);
        if ($menu === null) {
            return $this->refuse(__('Api::api.closed'));
        }
        if (! $user->guardRole($menu->id)) {
            return $this->refuse(__('Api::api.no_access', ['menu' => $menu->label()]));
        }
        if (! $user->hasMenuPermission($menu->id, CheckMenuPermission::PERMISSION_IDS[$permission])) {
            return $this->refuse(__('Api::api.not_allowed', [
                'verb' => __('Api::api.verbs.' . $permission), 'menu' => $menu->label(), 'permission' => $permission,
            ]));
        }

        return $next($request);
    }

    private function refuse(string $message): Response
    {
        return response()->json(['message' => $message], 403);
    }
}
