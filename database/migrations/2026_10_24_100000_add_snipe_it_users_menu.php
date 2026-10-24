<?php

use Database\Seeders\SnipeItUsersMenuSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the page "User" to the group "Snipe-IT" of a database that is already in use, and renames "Orang & Organisasi" to
 * "Organisasi" (see SnipeItUsersMenuSeeder). A new install gets both from SnipeItMenuSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new SnipeItUsersMenuSeeder())->run();
    }

    public function down(): void
    {
        SnipeItUsersMenuSeeder::remove();
    }
};
