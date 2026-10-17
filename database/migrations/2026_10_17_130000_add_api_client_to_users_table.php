<?php

use App\Models\UserManagement\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * An "API client" (page API Tokens) is an account of an application, not of a person: it holds a role and tokens, never
 * signs in on the screen, and is not listed under User Management. Being an account keeps the role, the history of
 * the radios ("Oleh") and the audit trail working for it as for a person.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'api_client')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('api_client')->default(false);
            });
        }
        Cache::forget(User::API_CLIENT_CACHE_KEY);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'api_client')) {
            // (Laravel's SQL Server grammar drops the default constraint of the column with it.)
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('api_client');
            });
        }
        Cache::forget(User::API_CLIENT_CACHE_KEY);
    }
};
