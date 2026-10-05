<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MenuRoleTableSeeder extends Seeder
{
    /**
     * Grant the super-admin role (id 1) access to every menu, including the
     * navigation groups and their children, so the guardRole()-driven sidebar
     * renders the full tree after a fresh seed.
     *
     * @return void
     */
    public function run()
    {
        \DB::table('menu_role')->delete();

        $now = '2024-03-19 07:48:53';

        // All menu ids seeded in MenuTableSeeder (roots + children).
        $menuIds = [1, 98, 99, 100, 101, 102, 103, 104, 105];

        $rows = array_map(fn ($menuId) => array(
            'menu_id' => $menuId,
            'role_id' => 1,
            'created_at' => $now,
            'updated_at' => null,
        ), $menuIds);

        \DB::table('menu_role')->insert($rows);
    }
}
