<?php

use App\Models\Log\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail (menu "Audit Log"): who signed in, and who changed what, with the values before and after. Written
 * by App\Services\Audit\AuditLogger, which leaves secrets out; never changed or deleted by the application.
 *
 * user_email and entity_label are copies, so a row stays readable once the account or the record is gone. user_id
 * has no foreign key for the same reason: the row must outlive the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();

                $table->string('action', 64);
                $table->string('entity_type', 64)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('entity_label')->nullable();

                $table->longText('old_values')->nullable();
                $table->longText('new_values')->nullable();

                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();

                $table->dateTime('created_at')->nullable();

                $table->index(['entity_type', 'entity_id']);
                $table->index(['user_id', 'created_at']);
                $table->index('action');
                $table->index('created_at');
            });
        }

        // The application asks whether the table is there at most once a minute: it is, from now on.
        Cache::forget(AuditLog::READY_CACHE_KEY);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Cache::forget(AuditLog::READY_CACHE_KEY);
    }
};
