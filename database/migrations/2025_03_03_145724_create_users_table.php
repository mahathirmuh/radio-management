<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('company_id')->index('users_company_id_foreign');
            $table->string('person_id')->nullable();
            $table->string('mark_user')->nullable();
            $table->string('verify_key');
            $table->longText('access_token')->nullable()->comment('access_token untuk login merdeka apps');
            $table->string('password');
            $table->string('active');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes()->comment('softDeletes');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
