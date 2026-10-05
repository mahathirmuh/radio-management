<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Radio Management: 1 row in `radios` = 1 physical radio (identified by SN).
 *
 * Foreign keys intentionally use NO ACTION: SQL Server rejects SET NULL/CASCADE
 * when two keys (owner_company_id + company_id) point at the same parent table.
 * Master data that is still in use is protected in the application instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('category', 10)->default('HT'); // HT (portable) | RIG (mobile)
            $table->string('power', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('radio_companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('radio_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('radio_channel_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('channels')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('radios', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('dmr_id')->nullable()->index();
            $table->string('serial_number', 50)->nullable()->index();
            $table->foreignId('radio_type_id')->nullable()->constrained('radio_types');
            $table->foreignId('owner_company_id')->nullable()->constrained('radio_companies');
            $table->string('user_name', 150)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('radio_departments');
            $table->foreignId('company_id')->nullable()->constrained('radio_companies');
            $table->foreignId('channel_template_id')->nullable()->constrained('radio_channel_templates');
            $table->date('last_programmed_at')->nullable();
            $table->string('placement', 20)->default('di_user')->index();
            $table->string('condition', 20)->default('belum_dicek')->index();
            $table->date('last_verified_at')->nullable();
            $table->string('document_no', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('needs_review')->default(false)->index();
            $table->text('review_notes')->nullable();
            $table->date('archived_at')->nullable()->index();
            $table->string('archive_reason', 50)->nullable();
            $table->unsignedInteger('previous_dmr_id')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->string('source_sheet', 50)->nullable();
            $table->json('source_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('radio_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radio_id')->constrained('radios');
            $table->date('event_date');
            $table->string('action', 50);
            $table->text('description')->nullable();
            $table->string('document_no', 100)->nullable();
            $table->json('changes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['radio_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_histories');
        Schema::dropIfExists('radios');
        Schema::dropIfExists('radio_channel_templates');
        Schema::dropIfExists('radio_departments');
        Schema::dropIfExists('radio_companies');
        Schema::dropIfExists('radio_types');
    }
};
