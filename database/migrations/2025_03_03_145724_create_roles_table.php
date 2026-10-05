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
        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->comment('Name of Role (SuperAdmin, Admin, User, .etc)');
            $table->unsignedBigInteger('company_id')->nullable()->index('roles_company_id_foreign');
            $table->string('home_page')->nullable();
            $table->string('func_role')->nullable();
            $table->string('setting_role')->nullable();
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
        Schema::dropIfExists('roles');
    }
};
