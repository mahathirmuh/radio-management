<?php

use Database\Seeders\OpnameMenuSeeder;
use Database\Seeders\RadioSectionMenuSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the sidebar entry "Stock Opname" to a database that is already in use (see OpnameMenuSeeder). A new install gets
 * it from DatabaseSeeder, after the menus of the radio module are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new OpnameMenuSeeder())->run();
    }

    public function down(): void
    {
        RadioSectionMenuSeeder::remove(OpnameMenuSeeder::ROUTE);
    }
};
