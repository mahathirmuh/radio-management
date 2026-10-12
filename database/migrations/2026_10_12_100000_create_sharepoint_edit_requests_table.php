<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The requests to change a cell of a SharePoint workbook (modules/SharePointDocs): somebody asks, somebody else
 * approves, and only then is the workbook changed. The table is also the record of who changed what, because
 * SharePoint itself says that the application did.
 *
 * The names of the people are kept in the row: an account can be removed later, the record of the change stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sharepoint_edit_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Which cell: the workbook (a key of config sharepoint-docs.workbooks), the sheet by its name, and the
            // cell in Excel's own terms. "line" is the row in Excel; "column_title" is what the sheet calls the column.
            $table->string('workbook', 60);
            $table->string('sheet', 200);
            $table->string('cell', 12);
            $table->unsignedInteger('line');
            $table->string('column_title', 200)->nullable();
            // The row is told by one other cell of it (its document number, say): when that is not what it was, the
            // row has moved and the change is not written.
            $table->string('anchor_cell', 12)->nullable();
            $table->string('anchor_value', 500)->nullable();

            // What the cell held when the change was asked for (the text as it is shown, and the number behind it
            // when the cell held one), and what it is to hold. new_kind: text, number or empty.
            // The numbers are kept as text: SQL Server hands a "float" back with 14 digits, and a number of 15 digits (an
            // IMEI in a cell that is a number) must come back as it went in.
            $table->string('old_text', 2000)->nullable();
            $table->string('old_number', 40)->nullable();
            $table->string('new_kind', 10);
            $table->string('new_value', 2000)->nullable();
            $table->string('new_number', 40)->nullable();

            // pending, processing, applied, rejected, conflict, failed
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('requested_by');
            $table->string('requested_by_name', 255);
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->string('decided_by_name', 255)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->dateTime('applied_at')->nullable();
            // Why a change was not written (a conflict, a failure), in words for the page.
            $table->string('failure', 500)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['workbook', 'sheet', 'cell', 'status'], 'sharepoint_edit_requests_cell_index');
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sharepoint_edit_requests');
    }
};
