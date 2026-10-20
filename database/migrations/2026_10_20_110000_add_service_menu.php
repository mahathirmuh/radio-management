<?php

use Database\Seeders\RadioSectionMenuSeeder;
use Database\Seeders\ServiceMenuSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the sidebar entry "Servis & Perbaikan" to a database that is already in use (see ServiceMenuSeeder). A new install
 * gets it from DatabaseSeeder, after the menus of the radio module are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ServiceMenuSeeder())->run();
    }

    public function down(): void
    {
        RadioSectionMenuSeeder::remove(ServiceMenuSeeder::ROUTE);
    }
};
