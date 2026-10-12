<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "E-Sign Master" (in the group "Master Data Radio", after its other pages): the signatures of
 * the people at ICT that go into the box "PROCESSED BY" of the IT Equipment Checkout Form.
 *
 * The entry is given to Super Admin only: whoever manages it handles other people's signatures. Other roles get it
 * on the Role & Permission page (unlike the other pages of the group, it is NOT given to Admin Radio by RadioMenuSeeder).
 *
 * Only adds what is missing: once the entry exists, its title, place and grants are the owner's. Does nothing while
 * the group is not there yet (a new install: the migration runs before the menus are seeded, DatabaseSeeder calls
 * this again after RadioMenuSeeder).
 *   php artisan db:seed --class=ESignMenuSeeder
 */
class ESignMenuSeeder extends Seeder
{
    public const ROUTE = 'radio-management.e-sign.index';

    public function run(): void
    {
        if (DB::table('menu')->where('route', self::ROUTE)->exists()) {
            return;
        }
        $group = DB::table('menu')->whereNull('route')->whereNull('parent_id')->where('title', 'Master Data Radio')->value('id');
        if ($group === null) {
            return;
        }

        $now = now();
        $menuId = (int) DB::table('menu')->insertGetId([
            'parent_id' => $group,
            'title' => 'E-Sign Master',
            'route' => self::ROUTE,
            'icon' => null,
            'order' => (int) DB::table('menu')->where('parent_id', $group)->max('order') + 1,
            'section' => null,
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
