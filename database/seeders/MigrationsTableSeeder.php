<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MigrationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('migrations')->delete();
        
        \DB::table('migrations')->insert(array (
            0 => 
            array (
                'id' => 1,
                'migration' => '2025_03_03_145724_create_activity_logs_table',
                'batch' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'migration' => '2025_03_03_145724_create_apps_table',
                'batch' => 0,
            ),
            2 => 
            array (
                'id' => 3,
                'migration' => '2025_03_03_145724_create_apps_user_table',
                'batch' => 0,
            ),
            3 => 
            array (
                'id' => 4,
                'migration' => '2025_03_03_145724_create_companies_table',
                'batch' => 0,
            ),
            4 => 
            array (
                'id' => 5,
                'migration' => '2025_03_03_145724_create_contractor_table',
                'batch' => 0,
            ),
            5 => 
            array (
                'id' => 6,
                'migration' => '2025_03_03_145724_create_error_logs_table',
                'batch' => 0,
            ),
            6 => 
            array (
                'id' => 7,
                'migration' => '2025_03_03_145724_create_failed_jobs_table',
                'batch' => 0,
            ),
            7 => 
            array (
                'id' => 8,
                'migration' => '2025_03_03_145724_create_jobs_table',
                'batch' => 0,
            ),
            8 => 
            array (
                'id' => 9,
                'migration' => '2025_03_03_145724_create_menu_table',
                'batch' => 0,
            ),
            9 => 
            array (
                'id' => 10,
                'migration' => '2025_03_03_145724_create_menu_role_table',
                'batch' => 0,
            ),
            10 => 
            array (
                'id' => 11,
                'migration' => '2025_03_03_145724_create_notifications_table',
                'batch' => 0,
            ),
            11 => 
            array (
                'id' => 12,
                'migration' => '2025_03_03_145724_create_password_reset_tokens_table',
                'batch' => 0,
            ),
            12 => 
            array (
                'id' => 13,
                'migration' => '2025_03_03_145724_create_password_resets_table',
                'batch' => 0,
            ),
            13 => 
            array (
                'id' => 14,
                'migration' => '2025_03_03_145724_create_permission_role_table',
                'batch' => 0,
            ),
            14 => 
            array (
                'id' => 15,
                'migration' => '2025_03_03_145724_create_permissions_table',
                'batch' => 0,
            ),
            15 => 
            array (
                'id' => 16,
                'migration' => '2025_03_03_145724_create_personal_access_tokens_table',
                'batch' => 0,
            ),
            16 => 
            array (
                'id' => 17,
                'migration' => '2025_03_03_145724_create_ref_industry_categories_table',
                'batch' => 0,
            ),
            17 => 
            array (
                'id' => 18,
                'migration' => '2025_03_03_145724_create_role_user_table',
                'batch' => 0,
            ),
            18 => 
            array (
                'id' => 19,
                'migration' => '2025_03_03_145724_create_roles_table',
                'batch' => 0,
            ),
            19 => 
            array (
                'id' => 20,
                'migration' => '2025_03_03_145724_create_site_table',
                'batch' => 0,
            ),
            20 => 
            array (
                'id' => 21,
                'migration' => '2025_03_03_145724_create_users_table',
                'batch' => 0,
            ),
            21 => 
            array (
                'id' => 22,
                'migration' => '2025_03_03_145725_create_apps_users_view',
                'batch' => 0,
            ),
            22 => 
            array (
                'id' => 23,
                'migration' => '2025_03_03_145725_create_merdeka_site_company_view',
                'batch' => 0,
            ),
            23 => 
            array (
                'id' => 24,
                'migration' => '2025_03_03_145725_create_merdeka_user_view',
                'batch' => 0,
            ),
            24 => 
            array (
                'id' => 25,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_apps_user_table',
                'batch' => 0,
            ),
            25 => 
            array (
                'id' => 26,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_companies_table',
                'batch' => 0,
            ),
            26 => 
            array (
                'id' => 27,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_contractor_table',
                'batch' => 0,
            ),
            27 => 
            array (
                'id' => 28,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_menu_role_table',
                'batch' => 0,
            ),
            28 => 
            array (
                'id' => 29,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_notifications_table',
                'batch' => 0,
            ),
            29 => 
            array (
                'id' => 30,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_permission_role_table',
                'batch' => 0,
            ),
            30 => 
            array (
                'id' => 31,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_role_user_table',
                'batch' => 0,
            ),
            31 => 
            array (
                'id' => 32,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_roles_table',
                'batch' => 0,
            ),
            32 => 
            array (
                'id' => 33,
                'migration' => '2025_03_03_145727_add_foreign_keys_to_users_table',
                'batch' => 0,
            ),
        ));
        
        
    }
}