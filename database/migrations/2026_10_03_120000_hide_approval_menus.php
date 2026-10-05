<?php

use Database\Seeders\HideApprovalMenusSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Hides the "Approval Management" branch of the sidebar on a database that is already in use
 * (see HideApprovalMenusSeeder: only role grants are removed, the menus themselves stay).
 * A new install gets the same from DatabaseSeeder, after the base grants have been seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new HideApprovalMenusSeeder())->run();
    }

    public function down(): void
    {
        // Grants are data: tick the Approval Management menus for a role on the
        // Role & Permission page to show them again.
    }
};
