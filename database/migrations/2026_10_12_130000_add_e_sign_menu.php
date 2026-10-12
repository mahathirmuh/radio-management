<?php

use Database\Seeders\ESignMenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "E-Sign Master" to a database that is already in use (see ESignMenuSeeder). A new install
 * gets it from DatabaseSeeder, after the menus of the radio module are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ESignMenuSeeder())->run();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->where('route', ESignMenuSeeder::ROUTE)->pluck('id');
        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();

        Cache::forget('rbac_menu_route_key_map');
    }
};
