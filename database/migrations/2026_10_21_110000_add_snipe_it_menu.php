<?php

use Database\Seeders\SnipeItMenuSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the sidebar group "Snipe-IT" and its pages to a database that is already in use (see SnipeItMenuSeeder). A new
 * install gets it from DatabaseSeeder, after the base menus are seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new SnipeItMenuSeeder())->run();
    }

    public function down(): void
    {
        SnipeItMenuSeeder::remove();
    }
};
