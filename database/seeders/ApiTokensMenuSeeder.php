<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "API Tokens" (section "Settings", right below "Audit Log"): the API clients and the tokens of
 * the API, shown by modules/Api.
 *
 * The entry is given to Super Admin only. A role that is given it on the Role & Permission page sees and makes only
 * tokens of its own (Create to make one, Delete to revoke one); the API clients and everybody's tokens stay Super
 * Admin's.
 *
 * Only adds what is missing: once the entry exists, its title, place and grants are the owner's. Does nothing on a
 * database whose base data is not seeded yet (see AuditLogMenuSeeder).
 *   php artisan db:seed --class=ApiTokensMenuSeeder
 */
class ApiTokensMenuSeeder extends Seeder
{
    public const ROUTE = 'api-tokens.index';

    public const TITLE = 'API Tokens';

    public function run(): void
    {
        if (DB::table('permissions')->doesntExist() || DB::table('menu')->where('route', self::ROUTE)->exists()) {
            return;
        }

        // Right below "Audit Log"; what sat there (the hidden "Approval Management") moves down by one.
        $audit = DB::table('menu')->whereNull('parent_id')->where('route', AuditLogMenuSeeder::ROUTE)->value('order');
        if ($audit !== null) {
            $order = (int) $audit + 1;
            DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->where('order', $order)->update(['order' => $order + 1]);
        } else {
            $order = (int) DB::table('menu')->whereNull('parent_id')->max('order') + 1;
        }

        $now = now();
        $menuId = (int) DB::table('menu')->insertGetId([
            'parent_id' => null,
            'title' => self::TITLE,
            'route' => self::ROUTE,
            'icon' => 'key',
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
