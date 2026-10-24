<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the page "User" (Snipe-IT's users, each with a detail page) to the group "Snipe-IT" of a database that is already in
 * use, right after "Aset". Until now the users were a tab of "Orang & Organisasi": whoever could open that page can open
 * "User" (its grants are copied), and the page is renamed "Organisasi" when it still has its first name.
 *
 * Only adds what is missing; does nothing without the group (a new install gets the page from SnipeItMenuSeeder).
 *   php artisan db:seed --class=SnipeItUsersMenuSeeder
 */
class SnipeItUsersMenuSeeder extends Seeder
{
    public const ROUTE = 'snipe-it.users.index';

    public const TITLE = 'User';

    /** The page that held the users, and its name before and after. */
    public const PEOPLE = 'snipe-it.people.index';

    public const PEOPLE_BEFORE = 'Orang & Organisasi';

    public const PEOPLE_AFTER = 'Organisasi';

    public function run(): void
    {
        $assets = DB::table('menu')->where('route', 'snipe-it.assets.index')->first(['id', 'parent_id', 'order']);
        if ($assets === null || $assets->parent_id === null || DB::table('menu')->where('route', self::ROUTE)->exists()) {
            return;
        }

        $now = now();
        $order = (int) $assets->order + 1;
        DB::table('menu')->where('parent_id', $assets->parent_id)->where('order', '>=', $order)->increment('order');
        $id = (int) DB::table('menu')->insertGetId([
            'parent_id' => $assets->parent_id,
            'title' => self::TITLE,
            'route' => self::ROUTE,
            'icon' => null,
            'order' => $order,
            'section' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $people = DB::table('menu')->where('route', self::PEOPLE)->value('id');
        if ($people !== null) {
            foreach (DB::table('menu_role')->where('menu_id', $people)->pluck('role_id') as $role) {
                DB::table('menu_role')->updateOrInsert(['menu_id' => $id, 'role_id' => $role], ['created_at' => $now, 'updated_at' => $now]);
            }
            foreach (DB::table('menu_role_permission')->where('menu_id', $people)->get(['role_id', 'permission_id']) as $grant) {
                DB::table('menu_role_permission')->updateOrInsert(
                    ['menu_id' => $id, 'role_id' => $grant->role_id, 'permission_id' => $grant->permission_id],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
            DB::table('menu')->where('id', $people)->where('title', self::PEOPLE_BEFORE)->update(['title' => self::PEOPLE_AFTER, 'updated_at' => $now]);
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }

    /** Takes the page and its grants away again, closes the room it took, and gives "Organisasi" its first name back. */
    public static function remove(): void
    {
        $page = DB::table('menu')->where('route', self::ROUTE)->first(['id', 'parent_id', 'order']);
        if ($page !== null) {
            DB::table('menu_role_permission')->where('menu_id', $page->id)->delete();
            DB::table('menu_role')->where('menu_id', $page->id)->delete();
            DB::table('menu')->where('id', $page->id)->delete();
            DB::table('menu')->where('parent_id', $page->parent_id)->where('order', '>', (int) $page->order)->decrement('order');
        }
        DB::table('menu')->where('route', self::PEOPLE)->where('title', self::PEOPLE_AFTER)->update(['title' => self::PEOPLE_BEFORE]);

        Cache::forget('rbac_menu_route_key_map');
    }
}
