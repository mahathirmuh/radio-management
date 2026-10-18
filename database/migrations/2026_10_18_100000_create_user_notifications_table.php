<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The notifications of the bell in the header (App\Services\Notifications\Notifier). A table of its own: the base app's
 * "notifications" table has another shape (and a required employee_id) and is used by nothing here; it stays as it is.
 * Unlike the audit trail, a notification goes with its account (the foreign key): it is nobody else's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            // What it is about (UserNotification::EDIT_REQUESTED, ...): the icon, and the tests.
            $table->string('kind', 40);
            $table->string('title', 255);
            $table->string('body', 1000)->nullable();
            // A path of this application ("/documents/edit-approvals?status=pending"), never another site.
            $table->string('url', 500)->nullable();
            // The same key is never given to the same person twice (a periodic notice); a one-off notice has a key of
            // its own. Never empty, so that one unique index does it on every database.
            $table->string('dedupe_key', 150);
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'read_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
