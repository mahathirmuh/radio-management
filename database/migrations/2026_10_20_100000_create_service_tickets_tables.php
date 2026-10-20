<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service and repairs (menu "Servis & Perbaikan"): a ticket for a radio that goes to a service center, and what happened
 * to it, step by step.
 *
 * No foreign key to `radios`: an import that starts over ("fresh") deletes every radio, and a ticket is a record of what
 * was done. The radio is copied into the ticket when it is made (its ID, SN and type). A fresh import is refused while a
 * ticket is open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            // SRV-0001, SRV-0002, ... (given out under the lock of the register).
            $table->string('number', 20)->unique();
            $table->unsignedBigInteger('radio_id')->nullable()->index();
            // The radio as it was when the ticket was made: "ID 751 · 903KXM2011", its Radio ID, SN and type.
            $table->string('radio_label', 255);
            $table->unsignedInteger('radio_dmr_id')->nullable();
            $table->string('radio_sn', 60)->nullable();
            $table->string('radio_type', 150)->nullable();
            // reported, sent, in_repair, ready, returned.
            $table->string('status', 20)->index();
            $table->string('issue', 500);
            // Who reported it: a person, or a unit (LV 12, POS 02), with the department.
            $table->string('reported_by', 150)->nullable();
            $table->string('service_center', 150)->nullable();
            // The BA the radio was sent with.
            $table->string('document_no', 60)->nullable();
            $table->date('sent_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->date('returned_at')->nullable();
            // What the service center asked, and what the repair cost in the end, in rupiah.
            $table->unsignedBigInteger('quote')->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            // Where the radio was before it was sent (Di User, Stok IT): offered again when it comes back.
            $table->string('placement_before', 20)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Every step of a ticket, and every note written on it.
        Schema::create('service_ticket_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('service_ticket_id')->index();
            // status (it moved on a step) or note.
            $table->string('kind', 20);
            $table->string('status', 20)->nullable();
            // What happened, in words ("Dikirim ke PT Contoh · BA 123"), or the note.
            $table->string('note', 1000)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Kept in the row: an account can be removed later, the record stays readable.
            $table->string('user_name', 255)->nullable();
            $table->dateTime('created_at');

            $table->foreign('service_ticket_id')->references('id')->on('service_tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_ticket_events');
        Schema::dropIfExists('service_tickets');
    }
};
