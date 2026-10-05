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
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index('notifications_user_id_foreign');
            $table->string('email')->nullable();
            $table->string('person_id')->nullable()->comment('related to employee table');
            $table->string('employee_id');
            $table->longText('title');
            $table->longText('description')->nullable();
            $table->boolean('status')->default(false);
            $table->string('color')->nullable();
            $table->integer('type')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
