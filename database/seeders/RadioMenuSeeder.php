<?php

namespace Database\Seeders;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "Radio" sidebar section and two roles:
 *  - Admin Radio  : full CRUD on every radio menu
 *  - Viewer Radio : read-only
 * Super Admin (role 1) is granted the menus too (it bypasses CRUD checks anyway).
 *
 * Idempotent, so it can also be run on an existing database:
 *   php artisan db:seed --class=RadioMenuSeeder
 */
class RadioMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $register = $this->menu(RadioSectionMenuSeeder::REGISTER, 'Register Radio', 'radio', 3, null, 'Radio');
        // The pages that came with the design refresh (Perlu Perhatian, Servis & Perbaikan, Stock Opname) are right below
        // it, when they are there: their seeders add them (after this one on a new install). The rest follows them.
        $sectionPages = [];
        foreach (RadioSectionMenuSeeder::ROUTES as $route) {
            $id = DB::table('menu')->where('route', $route)->value('id');
            if ($id) {
                DB::table('menu')->where('id', $id)->update(['order' => 4 + count($sectionPages), 'parent_id' => null, 'updated_at' => now()]);
                $sectionPages[] = (int) $id;
            }
        }
        $order = 4 + count($sectionPages);
        $history = $this->menu('radio-management.history.index', 'Riwayat Radio', 'history', $order);
        $masterGroup = $this->group('Master Data Radio', 'database', $order + 1);
        $masters = [
            $this->menu('radio-management.radio-type.index', 'Tipe Radio', null, 1, $masterGroup),
            $this->menu('radio-management.company.index', 'Perusahaan', null, 2, $masterGroup),
            $this->menu('radio-management.department.index', 'Department', null, 3, $masterGroup),
            $this->menu('radio-management.channel-template.index', 'Template Channel', null, 4, $masterGroup),
        ];
        $import = $this->menu('radio-management.import.index', 'Import Excel', 'file-import', $order + 2);

        // Keep the base sections below the radio section (3-6, and one more for each page above) and the "Dokumen"
        // entries (7-9, moved down as far).
        DB::table('menu')->whereNull('parent_id')->where('title', 'User Management')->update(['order' => 10 + count($sectionPages)]);
        DB::table('menu')->whereNull('parent_id')->where('title', 'Approval Management')->update(['order' => 11 + count($sectionPages)]);

        $dashboardId = (int) DB::table('menu')->where('route', 'dashboard.index')->value('id') ?: null;
        $routedMenus = array_merge([$register], $sectionPages, [$history, $import], $masters);
        $allMenus = array_filter(array_merge([$dashboardId, $masterGroup], $routedMenus));

        $superAdmin = 1;
        $adminIsNew = ! DB::table('roles')->where('name', 'Admin Radio')->whereNull('deleted_at')->exists();
        $admin = $this->role('Admin Radio');
        $viewer = $this->role('Viewer Radio');

        // The "Dokumen" entries belong to Admin Radio by default. Where they were added (by their
        // migrations) before this role existed, the default is applied here, at the role's
        // creation. For a role that was already there, the grant is whatever its owner made of it.
        if ($adminIsNew) {
            CofScanMenuSeeder::grantRead($admin);
            IctMasterlistMenuSeeder::grantRead($admin);
            EditApprovalsMenuSeeder::grantRead($admin);
            // Admin Radio may ask for a change of a cell of the masterlist (somebody else approves it).
            IctMasterlistMenuSeeder::grant($admin, ['Update']);
        }

        foreach ([$superAdmin, $admin, $viewer] as $roleId) {
            if (! DB::table('roles')->where('id', $roleId)->exists()) {
                continue;
            }
            foreach ($allMenus as $menuId) {
                DB::table('menu_role')->updateOrInsert(
                    ['menu_id' => $menuId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
            $permissions = $roleId === $viewer
                ? [CheckMenuPermission::PERMISSION_IDS['Read']]
                : array_values(CheckMenuPermission::PERMISSION_IDS);
            foreach (array_merge($routedMenus, array_filter([$dashboardId])) as $menuId) {
                foreach ($permissions as $permissionId) {
                    DB::table('menu_role_permission')->updateOrInsert(
                        ['menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => $permissionId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        }

        // The permission middleware caches the route => menu map.
        Cache::forget('rbac_menu_route_key_map');
    }

    private function menu(string $route, string $title, ?string $icon, int $order, ?int $parentId = null, ?string $section = null): int
    {
        $values = [
            'title' => $title,
            'icon' => $icon,
            'order' => $order,
            'parent_id' => $parentId,
            'section' => $section,
            'updated_at' => now(),
        ];
        $id = DB::table('menu')->where('route', $route)->value('id');
        if ($id) {
            DB::table('menu')->where('id', $id)->update($values);

            return (int) $id;
        }

        return (int) DB::table('menu')->insertGetId($values + ['route' => $route, 'created_at' => now()]);
    }

    private function group(string $title, string $icon, int $order): int
    {
        $id = DB::table('menu')->whereNull('route')->whereNull('parent_id')->where('title', $title)->value('id');
        if ($id) {
            DB::table('menu')->where('id', $id)->update(['icon' => $icon, 'order' => $order, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'title' => $title,
            'route' => null,
            'icon' => $icon,
            'order' => $order,
            'parent_id' => null,
            'section' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function role(string $name): int
    {
        $id = DB::table('roles')->where('name', $name)->whereNull('deleted_at')->value('id');

        return (int) ($id ?: DB::table('roles')->insertGetId([
            'name' => $name,
            'company_id' => null,
            'home_page' => '1',
            'func_role' => 'ALL',
            'setting_role' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
}
