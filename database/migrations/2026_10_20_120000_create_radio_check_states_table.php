<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Perlu Perhatian": what is known of an item of the register's checks besides the check itself, which is worked out on
 * every request (RadioCheckService). When it was first seen, to whom it was given, and why it was set aside.
 *
 * An item is one radio, or one SN / Radio ID that several radios share (the duplicates): `subject` is the radio's id or
 * that value. No foreign key to `radios`: the subject is not always a radio, and an import that starts over deletes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_check_states', function (Blueprint $table) {
            $table->bigIncrements('id');
            // A key of RadioCheckService::ISSUES (sn_duplicate, verify_stale, ...). ("check" is a word of SQL.)
            $table->string('check_code', 30);
            $table->string('subject', 100);
            $table->dateTime('first_seen_at');
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->dateTime('assigned_at')->nullable();
            // Set aside with a reason. It holds while the radios of the item are as they were then (the fingerprint of
            // their ids and versions): changed, the item is back.
            $table->string('dismissed_reason', 500)->nullable();
            $table->unsignedBigInteger('dismissed_by')->nullable();
            $table->dateTime('dismissed_at')->nullable();
            $table->string('dismissed_fingerprint', 64)->nullable();
            $table->timestamps();

            $table->unique(['check_code', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_check_states');
    }
};
