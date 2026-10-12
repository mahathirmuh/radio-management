<?php

use App\Http\Middleware\CheckMenuPermission;
use Database\Seeders\DocumentEditAccessSeeder;
use Database\Seeders\EditApprovalsMenuSeeder;
use Database\Seeders\IctMasterlistMenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the sidebar entry "Persetujuan Edit" to a database that is already in use (see EditApprovalsMenuSeeder), and
 * who may change the SharePoint workbook (see DocumentEditAccessSeeder): the role "Document Controller", and Update on
 * "ICT Masterlist" for it and for Admin Radio. A new install gets all of it from DatabaseSeeder, after the roles are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EditApprovalsMenuSeeder())->run();
        (new DocumentEditAccessSeeder())->run();
    }

    public function down(): void
    {
        $menuIds = DB::table('menu')->where('route', EditApprovalsMenuSeeder::ROUTE)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();

            // The two entries that made room move back up, when they are still where they were put.
            foreach (['User Management' => [10, 9], 'Approval Management' => [11, 10]] as $title => [$from, $to]) {
                DB::table('menu')->whereNull('parent_id')->where('title', $title)->where('order', $from)->update(['order' => $to]);
            }
        }

        // The right to change the masterlist goes (Admin Radio keeps reading it).
        $ict = DB::table('menu')->where('route', IctMasterlistMenuSeeder::ROUTE)->value('id');
        $roles = DB::table('roles')->whereIn('name', ['Admin Radio', DocumentEditAccessSeeder::DOCUMENT_CONTROLLER])->pluck('id');
        if ($ict !== null) {
            DB::table('menu_role_permission')->where('menu_id', $ict)
                ->where('permission_id', CheckMenuPermission::PERMISSION_IDS['Update'])
                ->whereIn('role_id', $roles)
                ->delete();
        }

        // The role "Document Controller" goes with what it was given, unless somebody has been put in it: then it stays as it is.
        $controller = DB::table('roles')->where('name', DocumentEditAccessSeeder::DOCUMENT_CONTROLLER)->value('id');
        if ($controller !== null && ! DB::table('role_user')->where('role_id', $controller)->exists()) {
            DB::table('menu_role_permission')->where('role_id', $controller)->delete();
            DB::table('menu_role')->where('role_id', $controller)->delete();
            DB::table('roles')->where('id', $controller)->delete();
        }

        Cache::forget('rbac_menu_route_key_map');
    }
};
