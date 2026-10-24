<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar group "Snipe-IT" (section "Aset IT", right after the section "Dokumen") and its pages: what is in
 * Snipe-IT, read only, shown by modules/SnipeIt.
 *
 * The group is given to Super Admin only, with Read (the pages change nothing): it shows every IT asset and the people
 * Snipe-IT knows. Other roles get it on the Role & Permission page (the group and the pages they may see).
 *
 * Only adds what is missing: once the group exists, its title, place and grants are the owner's. Does nothing on a
 * database whose base data is not seeded yet (see AuditLogMenuSeeder; DatabaseSeeder calls this again after it).
 *   php artisan db:seed --class=SnipeItMenuSeeder
 */
class SnipeItMenuSeeder extends Seeder
{
    public const TITLE = 'Snipe-IT';

    public const SECTION = 'Aset IT';

    /** The pages, in the order of the sidebar: route => title. ("User" came in 1.22.0: SnipeItUsersMenuSeeder.) */
    public const PAGES = [
        'snipe-it.overview.index' => 'Ringkasan',
        'snipe-it.assets.index' => 'Aset',
        'snipe-it.users.index' => 'User',
        'snipe-it.catalog.index' => 'Katalog',
        'snipe-it.people.index' => 'Organisasi',
        'snipe-it.stock.index' => 'Lisensi & Stok',
        'snipe-it.history.index' => 'Riwayat',
    ];

    public function run(): void
    {
        if (DB::table('permissions')->doesntExist() || DB::table('menu')->whereIn('route', array_keys(self::PAGES))->exists()) {
            return;
        }

        // After the last entry of "Dokumen"; without one, before "User Management"; else at the end. What is there moves down.
        $documents = DB::table('menu')->whereNull('parent_id')->where('section', 'Dokumen')->max('order');
        $users = DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->value('order');
        $order = match (true) {
            $documents !== null => (int) $documents + 1,
            $users !== null => (int) $users,
            default => (int) DB::table('menu')->whereNull('parent_id')->max('order') + 1,
        };

        $now = now();
        DB::table('menu')->whereNull('parent_id')->where('order', '>=', $order)->increment('order');
        $groupId = (int) DB::table('menu')->insertGetId([
            'parent_id' => null,
            'title' => self::TITLE,
            'route' => null,
            'icon' => 'packages',
            'order' => $order,
            'section' => self::SECTION,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $pageIds = [];
        $place = 0;
        foreach (self::PAGES as $route => $title) {
            $pageIds[] = (int) DB::table('menu')->insertGetId([
                'parent_id' => $groupId,
                'title' => $title,
                'route' => $route,
                'icon' => null,
                'order' => ++$place,
                'section' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Super Admin passes every check anyway; the rows are what puts the group into its sidebar.
        if (DB::table('roles')->where('id', 1)->exists()) {
            foreach ([$groupId, ...$pageIds] as $menuId) {
                DB::table('menu_role')->updateOrInsert(['menu_id' => $menuId, 'role_id' => 1], ['created_at' => $now, 'updated_at' => $now]);
            }
            $read = CheckMenuPermission::PERMISSION_IDS['Read'];
            if (DB::table('permissions')->where('id', $read)->exists()) {
                foreach ($pageIds as $menuId) {
                    DB::table('menu_role_permission')->updateOrInsert(
                        ['menu_id' => $menuId, 'role_id' => 1, 'permission_id' => $read],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }

    /** Takes the group, its pages and their grants away again, and closes the room it took (the migration's down()). */
    public static function remove(): void
    {
        $pages = DB::table('menu')->whereIn('route', array_keys(self::PAGES))->get(['id', 'parent_id']);
        $groups = DB::table('menu')->whereNull('parent_id')->whereIn('id', $pages->pluck('parent_id')->filter())->where('title', self::TITLE)->get(['id', 'order']);
        $menuIds = $pages->pluck('id')->merge($groups->pluck('id'));
        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $pages->pluck('id'))->delete();
        DB::table('menu')->whereIn('id', $groups->pluck('id'))->delete();
        foreach ($groups as $group) {
            DB::table('menu')->whereNull('parent_id')->where('order', '>', (int) $group->order)->decrement('order');
        }

        Cache::forget('rbac_menu_route_key_map');
    }
}
