<?php

use Illuminate\Support\Facades\Route;
use Modules\SharePointDocs\Http\Controllers\FolderController;
use Modules\SharePointDocs\Http\Controllers\WorkbookController;
use Modules\SharePointDocs\Http\Middleware\RequireMenu;

/*
| One page per folder in config('sharepoint-docs.folders') and per Excel workbook in
| config('sharepoint-docs.workbooks'). Route names follow "<menu key>.<action>"
| (documents.cof-scan.index / .list / .file, documents.ict-masterlist.index / .rows), so
| the `permission` middleware maps them to the page's menu: opening the page needs access
| to the menu, loading its data and showing a file need Read. RequireMenu closes them
| while that menu is missing.
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
        });
    }
});
