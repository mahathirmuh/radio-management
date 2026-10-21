<?php

use App\Models\UserManagement\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The language a person chose for the screens (App\Services\Language): "id" or "en", empty for whoever has not chosen
 * (Indonesian). Until this has run the choice is kept by the browser only, and nothing fails.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('locale', 5)->nullable();
            });
        }
        Cache::forget(User::LOCALE_CACHE_KEY);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
        Cache::forget(User::LOCALE_CACHE_KEY);
    }
};
