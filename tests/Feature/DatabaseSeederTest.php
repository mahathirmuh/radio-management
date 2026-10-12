<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesUsers;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_fresh_install_has_roles_and_menus_but_no_accounts(): void
    {
        $this->assertSame(0, User::withTrashed()->count());
        $this->assertSame(0, DB::table('role_user')->count());
        $this->assertSame(['Admin Radio', 'Document Controller', 'Super Admin', 'Viewer Radio'], DB::table('roles')->orderBy('name')->pluck('name')->all());
        $this->assertTrue(DB::table('menu')->where('route', 'radio-management.radio.index')->exists());
    }

    public function test_seeding_again_leaves_a_database_in_use_alone(): void
    {
        // What the owner set up after the install: a role of their own with its grants, a user,
        // and a menu taken away from a seeded role.
        $menuId = DB::table('menu')->where('route', 'radio-management.history.index')->value('id');
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Teknisi Radio', 'home_page' => '1', 'func_role' => 'ALL', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('menu_role')->insert(['menu_id' => $menuId, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('menu_role_permission')->insert([
            'menu_id' => $menuId, 'role_id' => $roleId, 'permission_id' => CheckMenuPermission::PERMISSION_IDS['Read'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = $this->makeUser('teknisi@merdekabattery.com', $roleId);
        DB::table('menu')->where('id', $menuId)->update(['title' => 'Log Radio']);

        $tables = ['roles', 'permissions', 'permission_role', 'site', 'companies', 'users', 'role_user'];
        $count = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
        $before = $count();

        $this->seed(); // php artisan db:seed, or the --seed of a later "migrate --seed"

        $this->assertSame($before, $count());
        $this->assertTrue(DB::table('roles')->where('id', $roleId)->exists());
        $this->assertTrue(DB::table('menu_role')->where(['menu_id' => $menuId, 'role_id' => $roleId])->exists());
        $this->assertTrue(DB::table('menu_role_permission')->where(['menu_id' => $menuId, 'role_id' => $roleId])->exists());
        $this->assertSame(['Teknisi Radio'], $user->fresh()->roles->pluck('name')->all());
        // The radio menus themselves are refreshed (titles, order, default grants of the radio roles).
        $this->assertSame('Riwayat Radio', DB::table('menu')->where('id', $menuId)->value('title'));
    }
}
