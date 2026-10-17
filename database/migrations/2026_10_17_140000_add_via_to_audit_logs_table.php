<?php

use App\Models\Log\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * How an entry of the audit trail came in: empty for the pages of the application, "API · <token> (#id)" for a request
 * of the API.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'via')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('via', 150)->nullable();
            });
        }
        Cache::forget(AuditLog::VIA_CACHE_KEY);
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'via')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn('via');
            });
        }
        Cache::forget(AuditLog::VIA_CACHE_KEY);
    }
};
