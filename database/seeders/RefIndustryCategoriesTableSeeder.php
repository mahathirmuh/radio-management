<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RefIndustryCategoriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('ref_industry_categories')->delete();
        
        \DB::table('ref_industry_categories')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Copper and Gold Mining',
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Copper Mining',
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Gold Mining',
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Metal Industry',
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Nickel',
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Power Plant',
                'created_at' => '2024-03-19 07:48:52',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}