<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

/**
 * The base app's approval module does not work in this application, so its menus are kept out
 * of the sidebar: by DatabaseSeeder on a new install, by a migration on a database in use.
 */
class HideApprovalMenusTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    /** @return \Illuminate\Support\Collection<int, int> ids of the approval pages and their group */
    private function approvalMenuIds()
    {
        $pages = DB::table('menu')->where('route', 'like', 'approval-management.%')->get(['id', 'parent_id']);
        $this->assertGreaterThanOrEqual(3, $pages->count(), 'The approval menus themselves must still exist.');

        return $pages->pluck('id')->merge($pages->pluck('parent_id')->filter())->unique()->values();
    }

    private function grantCount($menuIds): int
    {
        return DB::table('menu_role')->whereIn('menu_id', $menuIds)->count()
            + DB::table('menu_role_permission')->whereIn('menu_id', $menuIds)->count();
    }

    public function test_a_new_install_does_not_show_the_approval_menus(): void
    {
        $this->assertSame(0, $this->grantCount($this->approvalMenuIds()));

        $this->actingAs($this->superAdmin());
        $this->get(route('dashboard.index'))->assertOk()
            ->assertDontSee('Approval Management')
            ->assertSee('User Management')
            ->assertSee('Register Radio');
    }

    public function test_the_migration_hides_them_on_a_database_in_use_and_touches_nothing_else(): void
    {
        // A database from before this change: Super Admin and a custom role have the branch.
        $menuIds = $this->approvalMenuIds();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Approver', 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, $roleId] as $role) {
            foreach ($menuIds as $menuId) {
                DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $role, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('menu_role_permission')->insert([
                    'menu_id' => $menuId, 'role_id' => $role, 'permission_id' => CheckMenuPermission::PERMISSION_IDS['Read'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $others = fn () => [
            DB::table('menu')->count(),
            DB::table('menu_role')->whereNotIn('menu_id', $menuIds)->count(),
            DB::table('menu_role_permission')->whereNotIn('menu_id', $menuIds)->count(),
            DB::table('roles')->count(),
        ];
        $before = $others();
        $admin = $this->superAdmin();
        $approver = $this->makeUser('approver@merdekabattery.com', $roleId);

        $this->actingAs($admin)->get(route('dashboard.index'))->assertOk()->assertSee('Approval Management');

        $migration = require database_path('migrations/2026_10_03_120000_hide_approval_menus.php');
        $migration->up();
        $migration->up(); // running it twice changes nothing more

        $this->assertSame(0, $this->grantCount($menuIds));
        $this->assertSame($before, $others());
        $this->actingAs($admin->fresh())->get(route('dashboard.index'))->assertOk()
            ->assertDontSee('Approval Management')
            ->assertSee('User Management');
        // The pages are closed to a role that had them; the role itself is untouched.
        $this->actingAs($approver->fresh())->get(route('approval-management.approval-flows.index'))->assertForbidden();
        $this->assertSame(['Approver'], $approver->fresh()->roles->pluck('name')->all());

        // Ticking the group and its pages again on the Role & Permission page brings the branch back.
        $ticked = DB::table('menu_role')->where('role_id', 1)->pluck('menu_id')->merge($menuIds)->map(fn ($id) => (int) $id)->all();
        $this->actingAs($admin->fresh())->putJson(route('user-management.role-permission.update', 1), [
            'role_name' => 'Super Admin', 'role_type' => 'ALL', 'home_page' => '1', 'menu_list' => $ticked,
        ])->assertOk();
        $this->actingAs($admin->fresh())->get(route('dashboard.index'))->assertOk()->assertSee('Approval Management');
    }

    public function test_a_group_that_also_holds_other_pages_keeps_its_grant(): void
    {
        // Someone moved a working page under the approval group: the group must stay visible for it.
        $pages = DB::table('menu')->where('route', 'like', 'approval-management.%')->get(['id', 'parent_id']);
        $groupId = (int) $pages->pluck('parent_id')->filter()->unique()->sole();
        $historyId = (int) DB::table('menu')->where('route', 'radio-management.history.index')->value('id');
        DB::table('menu')->where('id', $historyId)->update(['parent_id' => $groupId]);
        foreach ($pages->pluck('id')->push($groupId) as $menuId) {
            DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        $migration = require database_path('migrations/2026_10_03_120000_hide_approval_menus.php');
        $migration->up();

        $this->assertSame(0, DB::table('menu_role')->whereIn('menu_id', $pages->pluck('id'))->count());
        $this->assertTrue(DB::table('menu_role')->where(['menu_id' => $groupId, 'role_id' => 1])->exists());
        $this->assertTrue(DB::table('menu_role')->where(['menu_id' => $historyId, 'role_id' => 1])->exists());
        $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()
            ->assertSee('Approval Management') // the group, now holding only Riwayat Radio
            ->assertSee('Riwayat Radio')
            ->assertDontSee('Approval Flows');
    }
}
