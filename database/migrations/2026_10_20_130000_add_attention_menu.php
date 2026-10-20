<?php

use Database\Seeders\AttentionMenuSeeder;
use Database\Seeders\RadioSectionMenuSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the sidebar entry "Perlu Perhatian" to a database that is already in use (see AttentionMenuSeeder). A new install
 * gets it from DatabaseSeeder, after the menus of the radio module are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new AttentionMenuSeeder())->run();
    }

    public function down(): void
    {
        RadioSectionMenuSeeder::remove(AttentionMenuSeeder::ROUTE);
    }
};
