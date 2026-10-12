<?php

namespace Modules\SharePointDocs\Services;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;

/**
 * Whether a user may use another page of this module than the one being asked for. A page that adds what
 * another page knows to its own answer (the folder's files told by the rows of a workbook, the rows told
 * by the files) may do so only for who may open that other page; otherwise the page would hand out what
 * its own menu was never granted.
 */
final class MenuAccess
{
    /**
     * Whether $user may use the page whose route is $routeName with $permission (Read, or Update for changing something):
     * Super Admin always; others when the page's menu is granted to one of their roles with that permission. Without a
     * menu row the page is closed to everyone but Super Admin (RequireMenu does the same).
     *
     * @param 'Read'|'Create'|'Update'|'Delete' $permission
     */
    public static function allows(?User $user, string $routeName, string $permission = 'Read'): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->roles->contains('id', 1)) {
            return true;
        }

        $menu = CheckMenuPermission::menuForRoute($routeName);

        return $menu !== null
            && $user->guardRole($menu->id)
            && $user->hasMenuPermission($menu->id, CheckMenuPermission::PERMISSION_IDS[$permission]);
    }
}
