<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Api\Models\ApiToken;

/**
 * The tokens of the API (Laravel Sanctum's table), made on the page "API Tokens". The token itself is kept only as a
 * SHA-256 hash; last_used_ip is this application's addition, shown next to last_used_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->dateTime('last_used_at')->nullable();
                $table->string('last_used_ip', 45)->nullable();
                $table->dateTime('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // The application asks whether the table is there at most once a minute: it is, from now on.
        Cache::forget(ApiToken::READY_CACHE_KEY);
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Cache::forget(ApiToken::READY_CACHE_KEY);
    }
};
