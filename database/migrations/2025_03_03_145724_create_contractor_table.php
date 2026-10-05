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
        Schema::create('contractor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id')->nullable()->index('contractor_site_id_foreign');
            $table->unsignedBigInteger('company_id')->nullable()->index('contractor_company_id_foreign');
            $table->string('contractor_code')->nullable();
            $table->string('contractor_name');
            $table->string('location')->nullable();
            $table->integer('is_active');
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
        Schema::dropIfExists('contractor');
    }
};
