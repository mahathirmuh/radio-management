<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The position of an account (IT Field, IT System Support, IT Assistant: see User::POSITIONS). User Management asks
 * for it where it used to ask for the company; an account from before has none until it is edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'position')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('position', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'position')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('position');
            });
        }
    }
};
