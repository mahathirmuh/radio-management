<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{

    /**
     * Only Super Admin comes from this seeder. The base app's HO / Site / Employee
     * roles are not used by Radio Management; "Admin Radio" and "Viewer Radio" are
     * created by RadioMenuSeeder.
     *
     * @return void
     */
    public function run()
    {


        \DB::table('roles')->delete();

        \DB::table('roles')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Super Admin',
                'company_id' => NULL,
                'home_page' => '1',
                'func_role' => 'ALL',
                'setting_role' => NULL,
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => '2024-03-08 15:17:00',
                'deleted_at' => NULL,
            ),
        ));


    }
}
