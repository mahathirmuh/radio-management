<?php

use Database\Seeders\IctMasterlistMenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "ICT Masterlist" to a database that is already in use (see
 * IctMasterlistMenuSeeder). A new install gets it from DatabaseSeeder, after the roles are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new IctMasterlistMenuSeeder())->run();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->pluck('id');
        if ($menuIds->isEmpty()) {
            return;
        }

        DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();

        // The two entries that made room move back up, when they are still where they were put.
        foreach (['User Management' => [9, 8], 'Approval Management' => [10, 9]] as $title => [$from, $to]) {
            DB::table('menu')->whereNull('parent_id')->where('title', $title)->where('order', $from)->update(['order' => $to]);
        }

        Cache::forget('rbac_menu_route_key_map');
    }
};
