<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SiteTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('site')->delete();
        
        \DB::table('site')->insert(array (
            0 => 
            array (
                'id' => 1,
                'site_code' => 'MCG',
                'site_name' => 'PT. MERDEKA COPPER GOLD',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logo_merdekaCG.png',
                'location' => 'JAKARTA',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'site_code' => 'DNS',
                'site_name' => 'PT. DWINAD NUSA SEJAHTERA',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logoSCG.png',
                'location' => 'SUMATERA SELATAN',
                'is_active' => 0,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'site_code' => 'BSI',
                'site_name' => 'PT. BUMI SUKSESINDO',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logoBSI.png',
                'location' => 'BANYUWANGI',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'site_code' => 'WETAR',
                'site_name' => 'WETAR',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/bkp_btr_logo.png',
                'location' => 'WETAR',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'site_code' => 'MBM',
                'site_name' => 'PT. MERDEKA BATTERY MATERIAL',
                'site_owner' => 'MBM',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logoMBM.png',
                'location' => 'JAKARTA',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'site_code' => 'MMS',
                'site_name' => 'PT. MERDEKA MINING SERVICE',
                'site_owner' => 'MMS',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logoMMS.png',
                'location' => 'MOROWALI',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'site_code' => 'MTI',
                'site_name' => 'MERDEKA TSINGSHAN INDONESIA',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/MTI.png',
                'location' => 'MOROWALI',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            7 => 
            array (
                'id' => 8,
                'site_code' => 'PANI',
                'site_name' => 'PUNCAK EMAS TANI SEJAHTERA',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logoPETS.png',
                'location' => 'GORONTALO',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            8 => 
            array (
                'id' => 9,
                'site_code' => 'SCM',
                'site_name' => 'PT. SULAWESI CAHAYA MINERAL',
                'site_owner' => 'MBM',
                'logo' => '/Content/assets/global/img/logo_perusahaan/scm.png',
                'location' => 'KONAWE',
                'is_active' => 1,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
            9 => 
            array (
                'id' => 10,
                'site_code' => 'SM',
                'site_name' => 'PT. SORIKMAS MINING',
                'site_owner' => 'MCG',
                'logo' => '/Content/assets/global/img/logo_perusahaan/logoSOR.png',
                'location' => 'SUMATERA UTARA',
                'is_active' => 0,
                'created_at' => '2024-03-19 07:48:53',
                'updated_at' => NULL,
                'deleted_at' => NULL,
            ),
        ));
        
        
    }
}