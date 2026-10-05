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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign(['ref_industry_category_id'])->references(['id'])->on('ref_industry_categories')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign(['site_id'])->references(['id'])->on('site')->onUpdate('CASCADE')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign('companies_ref_industry_category_id_foreign');
            $table->dropForeign('companies_site_id_foreign');
        });
    }
};
