<?php

use Database\Seeders\CofScanMenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "COF Scan" to a database that is already in use (see
 * CofScanMenuSeeder). A new install gets it from DatabaseSeeder, after the roles are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CofScanMenuSeeder())->run();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->where('route', CofScanMenuSeeder::ROUTE)->pluck('id');

        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();

        Cache::forget('rbac_menu_route_key_map');
    }
};
