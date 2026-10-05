<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioType;

/**
 * Baseline master data taken from the register's CODE and CH Classification sheets.
 * Idempotent: existing rows are left as they are. The Excel import adds whatever
 * else it finds (departments, extra templates, ...).
 */
class RadioMasterSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'MotoTrbo XiR P6620i', 'category' => 'HT', 'power' => '5 W'],
            ['name' => 'MotoTrbo XiR P6600i', 'category' => 'HT', 'power' => '5 W'],
            ['name' => 'MotoTrbo XiR C2660', 'category' => 'HT', 'power' => '5 W'],
            ['name' => 'MotoTrbo XiR P3680', 'category' => 'HT', 'power' => '5 W'],
            ['name' => 'MotoTrbo XiR M3688', 'category' => 'RIG', 'power' => '5 - 30 W'],
        ];
        foreach ($types as $type) {
            RadioType::firstOrCreate(['name' => $type['name']], $type);
        }

        RadioCompany::firstOrCreate(['code' => 'MTI'], ['name' => 'PT Merdeka Tsingshan Indonesia']);

        // "[NEW] Template HT & RIG" from the CH Classification sheet.
        $templates = [
            'General' => 'Semua channel kecuali IT',
            'Security' => 'Emergency, Security, Safety & Commissioning',
            'LV & BUS MTI' => 'Emergency, Security, Safety, Driver, Acid, Chloride, Pyrite, Makarti',
            'IT' => 'Semua channel',
            'Maintenance' => 'Emergency, Security, Safety, Maintenance, Acid, Chloride, Pyrite, Commissioning',
            'Survey' => 'Emergency, Survey, Konfirmasi',
            'EMG + Maintenance + Driver' => '1. Emergency, 2. Maintenance, 3. Driver',
            'EMG + Security (RPT & Direct)' => '1. Emergency, 2. Security (repeater), 3. Security (direct)',
        ];
        foreach ($templates as $name => $channels) {
            ChannelTemplate::firstOrCreate(['name' => $name], ['channels' => $channels]);
        }
    }
}
