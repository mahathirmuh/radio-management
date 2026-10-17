<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "Audit Log" (section "Settings", below "User Management"): the audit trail of sign-ins and
 * changes, shown by modules/AuditLog.
 *
 * The entry is given to Super Admin only: the trail shows who signed in from where and every change to accounts and
 * roles. Other roles get it on the Role & Permission page.
 *
 * Only adds what is missing: once the entry exists, its title, place and grants are the owner's. Does nothing on a
 * database whose base data is not seeded yet (a new install: the migration runs before the base seeders, which empty
 * the menu table and fill the roles; until then role id 1 may be another role. DatabaseSeeder calls this again after
 * them). `permissions` is filled by the base seed only, as DatabaseSeeder also goes by.
 *   php artisan db:seed --class=AuditLogMenuSeeder
 */
class AuditLogMenuSeeder extends Seeder
{
    public const ROUTE = 'audit-log.index';

    public const TITLE = 'Audit Log';

    /** Where the seeders leave "Approval Management": the entry takes that place and moves it down by one. */
    private const PLACE = 11;

    public function run(): void
    {
        if (DB::table('permissions')->doesntExist() || DB::table('menu')->where('route', self::ROUTE)->exists()) {
            return;
        }

        $approvals = DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->where('order', self::PLACE);
        if ($approvals->exists()) {
            $order = self::PLACE;
            $approvals->update(['order' => self::PLACE + 1]);
        } else {
            // The sidebar has been rearranged: the entry goes at the end.
            $order = (int) DB::table('menu')->whereNull('parent_id')->max('order') + 1;
        }

        $now = now();
        $menuId = (int) DB::table('menu')->insertGetId([
            'parent_id' => null,
            'title' => self::TITLE,
            'route' => self::ROUTE,
            'icon' => 'shield-search',
            'order' => $order,
            'section' => 'Settings',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Super Admin passes every check anyway; the rows are what puts the entry into its sidebar.
        if (DB::table('roles')->where('id', 1)->exists()) {
            DB::table('menu_role')->updateOrInsert(['menu_id' => $menuId, 'role_id' => 1], ['created_at' => $now, 'updated_at' => $now]);
            foreach (CheckMenuPermission::PERMISSION_IDS as $permissionId) {
                if (DB::table('permissions')->where('id', $permissionId)->exists()) {
                    DB::table('menu_role_permission')->updateOrInsert(
                        ['menu_id' => $menuId, 'role_id' => 1, 'permission_id' => $permissionId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }
}
