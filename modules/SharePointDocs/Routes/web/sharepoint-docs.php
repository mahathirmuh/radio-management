<?php

use Illuminate\Support\Facades\Route;
use Modules\SharePointDocs\Http\Controllers\EditApprovalController;
use Modules\SharePointDocs\Http\Controllers\FolderController;
use Modules\SharePointDocs\Http\Controllers\WorkbookController;
use Modules\SharePointDocs\Http\Middleware\RequireMenu;

/*
| One page per folder in config('sharepoint-docs.folders') and per Excel workbook in
| config('sharepoint-docs.workbooks'). Route names follow "<menu key>.<action>"
| (documents.cof-scan.index / .list / .file, documents.ict-masterlist.index / .rows), so
| the `permission` middleware maps them to the page's menu: opening the page needs access
| to the menu, loading its data and showing a file need Read, changing something (PATCH) needs
| Update. RequireMenu closes them while that menu is missing.
*/
Route::middleware(['web', 'auth', 'permission', RequireMenu::class])->prefix('documents')->name('documents.')->group(function () {
    foreach (array_keys(config('sharepoint-docs.folders', [])) as $folder) {
        Route::prefix($folder)->name("{$folder}.")->controller(FolderController::class)->group(function () use ($folder) {
            Route::get('/', 'index')->name('index')->defaults('folder', $folder);
            Route::get('list', 'list')->name('list')->defaults('folder', $folder);
            Route::get('file', 'file')->name('file')->defaults('folder', $folder);
        });
    }

    foreach (array_keys(config('sharepoint-docs.workbooks', [])) as $workbook) {
        Route::prefix($workbook)->name("{$workbook}.")->controller(WorkbookController::class)->group(function () use ($workbook) {
            Route::get('/', 'index')->name('index')->defaults('workbook', $workbook);
            Route::get('rows', 'rows')->name('rows')->defaults('workbook', $workbook);
            // Asks for a change of one cell: Update on this page's menu. Nothing is written to the workbook by it.
            Route::patch('edit', 'edit')->name('edit')->defaults('workbook', $workbook);
            // Writes cells of one row straight away: Update on this page's menu, and who may approve changes (K13h).
            Route::patch('row', 'writeRow')->name('write-row')->defaults('workbook', $workbook);
        });
    }

    // The requests to change a cell, and their approval (Update on this menu approves, Read lists them).
    Route::prefix('edit-approvals')->name('edit-approvals.')->controller(EditApprovalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
        Route::patch('{editRequest}/approve', 'approve')->whereNumber('editRequest')->name('approve');
        Route::patch('{editRequest}/reject', 'reject')->whereNumber('editRequest')->name('reject');
    });
});
