<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When an account last signed in, and how (Microsoft, Password, ...: see User::recordSignIn()). User Management shows
 * both; an account that has not signed in since this migration shows "Pending" and "Never".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dateTime('last_login_at')->nullable();
                $table->string('last_login_method', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['last_login_at', 'last_login_method']);
            });
        }
    }
};
