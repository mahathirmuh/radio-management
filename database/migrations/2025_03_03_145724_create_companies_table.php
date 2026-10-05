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
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id')->nullable()->index('companies_site_id_foreign');
            $table->unsignedBigInteger('ref_industry_category_id')->nullable()->index('companies_ref_industry_category_id_foreign');
            $table->string('company_code');
            $table->string('company_orangehr_code');
            $table->string('name')->comment('Name of Company');
            $table->string('short_name', 100);
            $table->string('location');
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
        Schema::dropIfExists('companies');
    }
};
