<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The signatures of the people at ICT ("E-Sign Master"), and the record of where each was put.
 *
 * A signature belongs to one account. The IT Equipment Checkout Form shows, in the box "PROCESSED BY", the
 * signature of the account that opens the form: nobody puts somebody else's signature on a form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_signatures', function (Blueprint $table) {
            $table->bigIncrements('id');
            // One signature per account.
            $table->unsignedBigInteger('user_id')->unique();
            // The name printed under the signature (the account's name unless another is given).
            $table->string('display_name', 150);
            // The picture (a PNG), encrypted with the application's key: it is never a file, and never in a public folder.
            $table->longText('image');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Every time a signature was put on a form: who opened the form (always the owner of the signature), and for which radio.
        Schema::create('it_signature_uses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('signature_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            // Kept in the row: an account or a radio can be removed later, the record stays.
            $table->string('user_name', 255);
            $table->unsignedBigInteger('radio_id')->nullable()->index();
            $table->string('radio_label', 255)->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_signature_uses');
        Schema::dropIfExists('it_signatures');
    }
};
