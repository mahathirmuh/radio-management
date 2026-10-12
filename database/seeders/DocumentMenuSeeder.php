<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds one sidebar entry of the section "Dokumen": a page of modules/SharePointDocs (a
 * SharePoint folder or workbook shown inside the application).
 *
 * The entry is given to Super Admin and Admin Radio, read only. Who may change something (ask for a
 * change of a cell, approve one) is given by DocumentEditAccessSeeder. Other roles get it on the
 * Role & Permission page.
 *
 * Only adds what is missing: once the entry exists, its title, place and grants are the
 * owner's, and running the seeder again changes nothing.
 */
abstract class DocumentMenuSeeder extends Seeder
{
    /** The route of the page: "documents.<key>.index". */
    public const ROUTE = '';

    protected const TITLE = '';

    protected const ICON = 'files';

    /** Place in the sidebar: after the radio menus (3-6), before User Management. */
    protected const ORDER = 7;

    public function run(): void
    {
        if (DB::table('menu')->where('route', static::ROUTE)->exists()) {
            return;
        }

        $now = now();
        DB::table('menu')->insert([
            'parent_id' => null,
            'title' => static::TITLE,
            'route' => static::ROUTE,
            'icon' => static::ICON,
            'order' => static::ORDER,
            'section' => 'Dokumen',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->added();

        // The roles that are there now. On a new install the migration runs before any role
        // exists and grants nothing; DatabaseSeeder then empties the menu table and calls this
        // again with the roles in place. A database that gets "Admin Radio" later (a base-app
        // database: migrate first, RadioMenuSeeder after) is served by RadioMenuSeeder, which
        // calls grantRead() for the role it creates.
        $roleIds = DB::table('roles')->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('id', 1)->orWhere('name', 'Admin Radio'))
            ->pluck('id');
        foreach ($roleIds as $roleId) {
            static::grantRead((int) $roleId);
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }

    /**
     * Gives a role the menu with Read. Does nothing while the menu (or the Read permission of
     * the base data) is not there; giving twice changes nothing.
     */
    public static function grantRead(int $roleId): void
    {
        static::grant($roleId, ['Read']);
    }

    /**
     * Gives a role the menu with these permissions ("Read", "Update" ...; see CheckMenuPermission::PERMISSION_IDS).
     * The role has the menu itself too, afterwards. Does nothing while the menu is not there, and skips a permission
     * the base data does not have; giving twice changes nothing.
     *
     * @param array<int, string> $permissions
     */
    public static function grant(int $roleId, array $permissions): void
    {
        $menuId = DB::table('menu')->where('route', static::ROUTE)->value('id');
        if ($menuId === null) {
            return;
        }

        $now = now();
        $given = false;
        foreach ($permissions as $name) {
            $permissionId = CheckMenuPermission::PERMISSION_IDS[$name] ?? null;
            if ($permissionId === null || ! DB::table('permissions')->where('id', $permissionId)->exists()) {
                continue;
            }
            DB::table('menu_role_permission')->updateOrInsert(
                ['menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now]
            );
            $given = true;
        }
        if ($given) {
            DB::table('menu_role')->updateOrInsert(
                ['menu_id' => $menuId, 'role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    /** Called once, right after the entry was added: for what else has to move to make room for it. */
    protected function added(): void
    {
    }
}
