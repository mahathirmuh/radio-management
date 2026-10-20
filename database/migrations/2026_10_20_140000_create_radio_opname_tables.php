<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock opname (menu "Stock Opname"): a count of the radios of one department, done on a phone. Every radio found is
 * saved as it is scanned (a session of the application ends after 30 minutes without a request; what was scanned stays),
 * and sending the session verifies them today in the register.
 *
 * No foreign key to `radios`: an import that starts over deletes them (it is refused while a session is open). The names
 * of the department, the company and the radio are copied in, so that a session stays readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radio_opname_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Where it is counted: one department, of one company or of all of them.
            $table->unsignedBigInteger('department_id')->index();
            $table->string('department_name', 150);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('company_name', 150)->nullable();
            // open, submitted.
            $table->string('status', 20)->index();
            // How many radios the register had there: when it started, and when it was sent.
            $table->unsignedInteger('expected');
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->dateTime('submitted_at')->nullable();
            // What sending it did: verified, not found, other holder, skipped (JSON).
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        // A radio found in a session: who has it, in what condition.
        Schema::create('radio_opname_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('session_id')->index();
            $table->unsignedBigInteger('radio_id');
            $table->string('radio_label', 255);
            // register (its user in the register), other (somebody else, named), none (nobody: on a shelf).
            $table->string('holder', 20);
            $table->string('holder_name', 150)->nullable();
            $table->string('condition', 20);
            $table->string('note', 500)->nullable();
            // A service ticket opened for it from the session.
            $table->string('ticket_number', 20)->nullable();
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'radio_id']);
            $table->foreign('session_id')->references('id')->on('radio_opname_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_opname_items');
        Schema::dropIfExists('radio_opname_sessions');
    }
};
