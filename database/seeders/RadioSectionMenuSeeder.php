<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A page of the sidebar's "Radio" section that came with the design refresh (Perlu Perhatian, Servis & Perbaikan, Stock
 * Opname). They go right below Register Radio, in the order of ROUTES, above Riwayat Radio: whatever is at that place
 * and below moves down by one to make room.
 *
 * The page is given to whoever has Register Radio, with the same permissions (Admin Radio all of them, Viewer Radio
 * Read, Super Admin all): the people who keep the register keep these too. The owner changes that on Role & Permission.
 *
 * Only adds what is missing: once the entry exists, its title and grants are the owner's (RadioMenuSeeder puts the
 * section back in its order). Does nothing while Register Radio is not there yet (a new install: the migration runs
 * before the menus are seeded, DatabaseSeeder calls this again after the other menus).
 */
abstract class RadioSectionMenuSeeder extends Seeder
{
    /** The pages, in their order in the sidebar. */
    public const ROUTES = [
        'radio-management.attention.index',
        'radio-management.service.index',
        'radio-management.opname.index',
    ];

    public const REGISTER = 'radio-management.radio.index';

    /** The route of the page, one of ROUTES. */
    abstract protected function route(): string;

    abstract protected function title(): string;

    /** A Tabler icon, without "ti-". */
    abstract protected function icon(): string;

    public function run(): void
    {
        if (DB::table('menu')->where('route', $this->route())->exists()) {
            return;
        }
        $register = DB::table('menu')->where('route', self::REGISTER)->first(['id', 'order']);
        if ($register === null) {
            return;
        }

        // Below Register Radio, or below the last page of the list above this one that is there.
        $above = array_slice(self::ROUTES, 0, (int) array_search($this->route(), self::ROUTES, true));
        $after = max((int) $register->order, (int) DB::table('menu')->whereNull('parent_id')->whereIn('route', $above)->max('order'));
        $order = $after + 1;

        $now = now();
        DB::table('menu')->whereNull('parent_id')->where('order', '>=', $order)->increment('order');
        $menuId = (int) DB::table('menu')->insertGetId([
            'parent_id' => null,
            'title' => $this->title(),
            'route' => $this->route(),
            'icon' => $this->icon(),
            'order' => $order,
            'section' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('menu_role')->where('menu_id', $register->id)->pluck('role_id') as $roleId) {
            DB::table('menu_role')->updateOrInsert(['menu_id' => $menuId, 'role_id' => $roleId], ['created_at' => $now, 'updated_at' => $now]);
        }
        foreach (DB::table('menu_role_permission')->where('menu_id', $register->id)->get(['role_id', 'permission_id']) as $grant) {
            DB::table('menu_role_permission')->updateOrInsert(
                ['menu_id' => $menuId, 'role_id' => $grant->role_id, 'permission_id' => $grant->permission_id],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }

    /** Takes the entry and its grants away again, and closes the room it took (the migration's down()). */
    public static function remove(string $route): void
    {
        $entries = DB::table('menu')->where('route', $route)->get(['id', 'order', 'parent_id']);
        if ($entries->isEmpty()) {
            return;
        }

        $menuIds = $entries->pluck('id');
        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();

        foreach ($entries->whereNull('parent_id') as $entry) {
            DB::table('menu')->whereNull('parent_id')->where('order', '>', (int) $entry->order)->decrement('order');
        }

        Cache::forget('rbac_menu_route_key_map');
    }
}
