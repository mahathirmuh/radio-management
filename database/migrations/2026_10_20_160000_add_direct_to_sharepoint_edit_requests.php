<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A change of a SharePoint workbook written straight away, by somebody who may approve changes too (K13h: the Document
 * Controller, Super Admin), instead of asked for and approved by somebody else. It is kept as a request applied by the
 * one who made it, marked "direct", so that the page of the approvals and the audit trail still show it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sharepoint_edit_requests', function (Blueprint $table) {
            $table->boolean('direct')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('sharepoint_edit_requests', function (Blueprint $table) {
            $table->dropColumn('direct');
        });
    }
};
