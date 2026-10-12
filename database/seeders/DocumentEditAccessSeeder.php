<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Who may change the SharePoint workbook (ICT Masterlist), and who decides on it:
 *
 *  - Super Admin: everything, as always.
 *  - Admin Radio: asks for a change of a cell (Update on "ICT Masterlist"); sees the requests (Read on "Persetujuan Edit").
 *  - Document Controller (the role is made here when it is not there yet): reads COF Scan and ICT Masterlist, asks
 *    for a change of a cell, and approves or turns down requests (Update on "Persetujuan Edit").
 *
 * Nobody approves their own request, whatever they may do. Giving is all this does: it never takes a permission away
 * (an owner who took one away on the Role & Permission page has it back when this runs again; the migration that
 * runs it runs once).
 *   php artisan db:seed --class=DocumentEditAccessSeeder
 */
class DocumentEditAccessSeeder extends Seeder
{
    public const DOCUMENT_CONTROLLER = 'Document Controller';

    public function run(): void
    {
        $now = now();

        $adminId = DB::table('roles')->where('name', 'Admin Radio')->whereNull('deleted_at')->value('id');
        if ($adminId !== null) {
            IctMasterlistMenuSeeder::grant((int) $adminId, ['Read', 'Update']);
            EditApprovalsMenuSeeder::grantRead((int) $adminId);
        }

        $controller = (int) (DB::table('roles')->where('name', self::DOCUMENT_CONTROLLER)->whereNull('deleted_at')->value('id')
            ?: DB::table('roles')->insertGetId([
                'name' => self::DOCUMENT_CONTROLLER,
                'company_id' => null,
                'home_page' => '1',
                'func_role' => 'ALL',
                'setting_role' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        CofScanMenuSeeder::grantRead($controller);
        IctMasterlistMenuSeeder::grant($controller, ['Read', 'Update']);
        EditApprovalsMenuSeeder::grant($controller, ['Read', 'Update']);

        // The dashboard is the page everybody lands on after signing in: it is open to the role, read only.
        $dashboard = DB::table('menu')->where('route', 'dashboard.index')->value('id');
        $read = CheckMenuPermission::PERMISSION_IDS['Read'];
        if ($dashboard !== null && DB::table('permissions')->where('id', $read)->exists()) {
            DB::table('menu_role')->updateOrInsert(
                ['menu_id' => $dashboard, 'role_id' => $controller],
                ['created_at' => $now, 'updated_at' => $now]
            );
            DB::table('menu_role_permission')->updateOrInsert(
                ['menu_id' => $dashboard, 'role_id' => $controller, 'permission_id' => $read],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }
}
