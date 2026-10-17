<?php

use Database\Seeders\AuditLogMenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "Audit Log" to a database that is already in use (see AuditLogMenuSeeder). A new install gets
 * it from DatabaseSeeder, after the other menus are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AuditLogMenuSeeder())->run();
    }

    public function down(): void
    {
        $menus = DB::table('menu')->where('route', AuditLogMenuSeeder::ROUTE)->get(['id', 'order']);
        if ($menus->isEmpty()) {
            return;
        }
        $menuIds = $menus->pluck('id');

        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();

        // "Approval Management" goes back to the place the entry took, when it is still right below it.
        $place = (int) $menus->first()->order;
        DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->where('order', $place + 1)->update(['order' => $place]);

        Cache::forget('rbac_menu_route_key_map');
    }
};
