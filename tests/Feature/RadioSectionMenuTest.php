<?php

namespace Tests\Feature;

use Database\Seeders\AttentionMenuSeeder;
use Database\Seeders\OpnameMenuSeeder;
use Database\Seeders\RadioMenuSeeder;
use Database\Seeders\RadioSectionMenuSeeder;
use Database\Seeders\ServiceMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The three pages the design refresh adds to the sidebar's "Radio" section (Perlu Perhatian, Servis & Perbaikan, Stock
 * Opname): on a database that is already in use, their migrations put them where a new install has them, below Register
 * Radio and in that order, and given like it.
 */
class RadioSectionMenuTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** The migrations of the three entries, in the order they run. */
    private const MIGRATIONS = ['2026_10_20_110000_add_service_menu', '2026_10_20_130000_add_attention_menu', '2026_10_20_150000_add_opname_menu'];

    /** @return array<int, array{0: string, 1: int}> the top entries of the sidebar and their numbers, in their order */
    private function sidebar(): array
    {
        return DB::table('menu')->whereNull('parent_id')->orderBy('order')->get(['title', 'order'])
            ->map(fn ($entry) => [$entry->title, (int) $entry->order])->all();
    }

    /** @return array<int, string> who may do what on a page, as "role:permission" */
    private function grants(string $route): array
    {
        $menu = DB::table('menu')->where('route', $route)->value('id');

        return DB::table('menu_role_permission')->join('roles', 'roles.id', '=', 'menu_role_permission.role_id')
            ->where('menu_role_permission.menu_id', $menu)->orderBy('roles.name')->orderBy('menu_role_permission.permission_id')
            ->get(['roles.name', 'menu_role_permission.permission_id'])
            ->map(fn ($grant) => $grant->name . ':' . $grant->permission_id)
            ->merge(DB::table('menu_role')->join('roles', 'roles.id', '=', 'menu_role.role_id')->where('menu_role.menu_id', $menu)
                ->orderBy('roles.name')->pluck('roles.name')->map(fn ($role) => $role . ':menu'))
            ->all();
    }

    public function test_a_database_in_use_gets_the_three_pages_where_a_new_install_has_them(): void
    {
        $installed = $this->sidebar();
        $titles = array_column($installed, 0);
        $at = array_search('Register Radio', $titles, true);
        $this->assertSame(['Register Radio', 'Perlu Perhatian', 'Servis & Perbaikan', 'Stock Opname', 'Riwayat Radio'], array_slice($titles, $at, 5));
        $this->assertSame(count($installed), count(array_unique(array_column($installed, 1))), 'no two entries share a number');

        // As before the design refresh: none of the three, and no gap where they are.
        foreach (RadioSectionMenuSeeder::ROUTES as $route) {
            RadioSectionMenuSeeder::remove($route);
        }
        $before = $this->sidebar();
        $this->assertSame(['Register Radio', 'Riwayat Radio'], array_slice(array_column($before, 0), $at, 2));
        $this->assertSame($before[$at][1] + 1, $before[$at + 1][1]);

        // The migrations in the order they run (Servis & Perbaikan first, though it ends up second): the sidebar of a new install,
        // and the map of the menus that the permission middleware keeps is made again.
        Cache::put('rbac_menu_route_key_map', ['kept' => 'old']);
        $migrations = array_map(fn (string $file) => require database_path("migrations/{$file}.php"), self::MIGRATIONS);
        foreach ($migrations as $migration) {
            $migration->up();
        }
        $this->assertSame($installed, $this->sidebar());
        $this->assertFalse(Cache::has('rbac_menu_route_key_map'));
        foreach (RadioSectionMenuSeeder::ROUTES as $route) {
            $this->assertSame($this->grants(RadioSectionMenuSeeder::REGISTER), $this->grants($route), $route);
        }

        // Seeding again changes nothing; the migrations taken back leave the sidebar as it was.
        (new RadioMenuSeeder())->run();
        foreach ([new AttentionMenuSeeder(), new ServiceMenuSeeder(), new OpnameMenuSeeder()] as $seeder) {
            $seeder->run();
        }
        $this->assertSame($installed, $this->sidebar());
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $this->assertSame($before, $this->sidebar());
    }
}
