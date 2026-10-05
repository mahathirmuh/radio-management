<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // SQL Server rejects these cascades: site -> companies -> contractor and
        // site -> contractor would be two cascade paths into the same table.
        $action = DB::getDriverName() === 'sqlsrv' ? 'NO ACTION' : 'CASCADE';

        Schema::table('contractor', function (Blueprint $table) use ($action) {
            $table->foreign(['company_id'])->references(['id'])->on('companies')->onUpdate($action)->onDelete($action);
            $table->foreign(['site_id'])->references(['id'])->on('site')->onUpdate($action)->onDelete($action);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contractor', function (Blueprint $table) {
            $table->dropForeign('contractor_company_id_foreign');
            $table->dropForeign('contractor_site_id_foreign');
        });
    }
};
