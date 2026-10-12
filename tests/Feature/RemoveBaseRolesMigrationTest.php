<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

class RemoveBaseRolesMigrationTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    private function migration()
    {
        return require database_path('migrations/2026_10_03_110000_remove_unused_base_roles.php');
    }

    private function addBaseRole(string $name, ?string $deletedAt = null): int
    {
        return (int) DB::table('roles')->insertGetId([
            'name' => $name,
            'home_page' => '1',
            'func_role' => 'SITE',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $deletedAt,
        ]);
    }

    public function test_it_removes_the_unused_base_roles_and_keeps_the_rest(): void
    {
        $this->addBaseRole('Admin HO - MCG');
        $this->addBaseRole("Employee's");
        $withMenu = $this->addBaseRole('Admin Site - MTI');
        $this->addBaseRole('Admin Site - MTI Beijing');
        $this->addBaseRole('Admin Site - MTI Beijing', now()->toDateTimeString()); // soft-deleted duplicate in the seed
        $custom = $this->addBaseRole('Teknisi Radio'); // created by the owner: must survive
        DB::table('menu_role')->insert(['menu_id' => 1, 'role_id' => $withMenu, 'created_at' => now(), 'updated_at' => now()]);

        $this->migration()->up();

        $this->assertSame(
            ['Admin Radio', 'Document Controller', 'Super Admin', 'Teknisi Radio', 'Viewer Radio'],
            DB::table('roles')->orderBy('name')->pluck('name')->all()
        );
        $this->assertSame(0, DB::table('menu_role')->where('role_id', $withMenu)->count());
        $this->assertTrue(DB::table('roles')->where('id', $custom)->exists());

        // Running it again (or on a fresh install) is a no-op.
        $this->migration()->up();
        $this->assertSame(5, DB::table('roles')->count());
    }

    public function test_an_approval_step_that_named_a_removed_role_does_not_block_the_clean_up(): void
    {
        $role = $this->addBaseRole('Admin Site - MTI');
        $flow = DB::table('approval_flows')->insertGetId(['name' => 'Flow', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $step = DB::table('approval_steps')->insertGetId([
            'name' => 'Step 1', 'approval_flow_id' => $flow, 'step_order' => 1, 'role_id' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $this->assertFalse(DB::table('roles')->where('id', $role)->exists());
        $this->assertNull(DB::table('approval_steps')->where('id', $step)->value('role_id'));
    }

    public function test_it_refuses_while_a_user_still_holds_one_of_the_roles(): void
    {
        $role = $this->addBaseRole('Admin Site - MTI');
        $this->makeUser('site.admin@test.local', $role);

        try {
            $this->migration()->up();
            $this->fail('The migration should refuse to strand a user.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('1 user assignment', $e->getMessage());
        }

        $this->assertTrue(DB::table('roles')->where('id', $role)->exists());
    }
}
