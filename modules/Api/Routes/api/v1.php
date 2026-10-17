<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\V1\AuditLogController;
use Modules\Api\Http\Controllers\V1\DashboardController;
use Modules\Api\Http\Controllers\V1\DocumentFolderController;
use Modules\Api\Http\Controllers\V1\DocumentWorkbookController;
use Modules\Api\Http\Controllers\V1\EditRequestController;
use Modules\Api\Http\Controllers\V1\ESignController;
use Modules\Api\Http\Controllers\V1\HistoryController;
use Modules\Api\Http\Controllers\V1\ImportController;
use Modules\Api\Http\Controllers\V1\MasterDataController;
use Modules\Api\Http\Controllers\V1\MeController;
use Modules\Api\Http\Controllers\V1\RadioController;
use Modules\Api\Http\Controllers\V1\ReferenceController;
use Modules\Api\Http\Controllers\V1\RoleController;
use Modules\Api\Http\Controllers\V1\UserController;
use Modules\Api\Http\Middleware\ApiAccess;
use Modules\Api\Http\Middleware\ApiReady;
use Modules\Api\Http\Middleware\EnsureApiAccount;
use Modules\Api\Http\Middleware\ForceJsonResponse;
use Modules\Api\Http\Middleware\LimitAddresses;
use Modules\Api\Http\Middleware\PersonOnly;

/*
| The API, version 1: https://<host>/api/v1/..., a Bearer token from the page "API Tokens".
|
| Every endpoint belongs to the menu of its page and needs the permission of the method on it (ApiAccess): reading the
| radios is Read on "Register Radio", archiving one is an Update on it. Not the `permission` middleware of the pages:
| a route without a menu is closed here. Limits: 120 requests a minute per address before the token is looked at,
| 60 a minute per token after.
*/
Route::prefix('api/v1')->name('api.v1.')
    ->middleware([ForceJsonResponse::class, LimitAddresses::class, ApiReady::class, 'auth:sanctum', EnsureApiAccount::class, 'throttle:api-v1', SubstituteBindings::class])
    ->group(function () {
        Route::get('me', [MeController::class, 'show'])->name('me');
        Route::get('references', [ReferenceController::class, 'index'])->name('references');
        Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard')->middleware(ApiAccess::to('dashboard'));

        $radios = 'radio-management.radio';
        Route::prefix('radios')->name('radios.')->controller(RadioController::class)->group(function () use ($radios) {
            Route::get('/', 'index')->name('index')->middleware(ApiAccess::to($radios));
            Route::post('/', 'store')->name('store')->middleware(ApiAccess::to($radios));
            Route::get('free-ids', 'freeIds')->name('free-ids')->middleware(ApiAccess::to($radios));
            Route::get('check', 'check')->name('check')->middleware(ApiAccess::to($radios));
            // The button "Export" of the page: Read, and the limit for heavy work.
            Route::get('export', 'export')->name('export')->middleware([ApiAccess::to($radios), 'throttle:api-v1-heavy']);
            Route::get('{radio}/checkout-form', 'checkoutForm')->whereNumber('radio')->name('checkout-form')->middleware(ApiAccess::to($radios));
            Route::get('{radio}', 'show')->whereNumber('radio')->name('show')->middleware(ApiAccess::to($radios));
            Route::put('{radio}', 'update')->whereNumber('radio')->name('update')->middleware(ApiAccess::to($radios));
            Route::delete('{radio}', 'destroy')->whereNumber('radio')->name('destroy')->middleware(ApiAccess::to($radios));
            // Changes of another kind: Update, as the buttons of the page (PUT there).
            Route::post('{radio}/archive', 'archive')->whereNumber('radio')->name('archive')->middleware(ApiAccess::to($radios, 'Update'));
            Route::post('{radio}/restore', 'restore')->whereNumber('radio')->name('restore')->middleware(ApiAccess::to($radios, 'Update'));
            Route::get('{radio}/history', 'history')->whereNumber('radio')->name('history')->middleware(ApiAccess::to($radios));
            Route::post('{radio}/history', 'addHistory')->whereNumber('radio')->name('add-history')->middleware(ApiAccess::to($radios, 'Update'));
        });

        Route::get('history', [HistoryController::class, 'index'])->name('history')->middleware(ApiAccess::to('radio-management.history'));

        // "Import Excel": the last report (Read), an import (Create; replacing the register also needs Delete on
        // "Register Radio", checked by the controller as on the page).
        Route::get('import', [ImportController::class, 'show'])->name('import.show')->middleware(ApiAccess::to('radio-management.import'));
        Route::post('import', [ImportController::class, 'store'])->name('import.store')->middleware([ApiAccess::to('radio-management.import'), 'throttle:api-v1-heavy']);

        // User Management and Role & Permission, read only.
        Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware(ApiAccess::to('user-management.user-registered'));
        Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->name('users.show')->middleware(ApiAccess::to('user-management.user-registered'));
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware(ApiAccess::to('user-management.role-permission'));
        Route::get('roles/{role}', [RoleController::class, 'show'])->whereNumber('role')->name('roles.show')->middleware(ApiAccess::to('user-management.role-permission'));

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log')->middleware(ApiAccess::to('audit-log'));

        // The four master tables, each with the menu of its page ("Tipe Radio", "Perusahaan", ...).
        foreach (['radio-types' => 'radio-type', 'companies' => 'company', 'departments' => 'department', 'channel-templates' => 'channel-template'] as $path => $master) {
            $menu = "radio-management.{$master}";
            Route::prefix($path)->name("{$path}.")->controller(MasterDataController::class)->group(function () use ($master, $menu) {
                Route::get('/', 'index')->name('index')->defaults('master', $master)->middleware(ApiAccess::to($menu));
                Route::post('/', 'store')->name('store')->defaults('master', $master)->middleware(ApiAccess::to($menu));
                Route::get('{id}', 'show')->whereNumber('id')->name('show')->defaults('master', $master)->middleware(ApiAccess::to($menu));
                Route::put('{id}', 'update')->whereNumber('id')->name('update')->defaults('master', $master)->middleware(ApiAccess::to($menu));
                Route::delete('{id}', 'destroy')->whereNumber('id')->name('destroy')->defaults('master', $master)->middleware(ApiAccess::to($menu));
            });
        }

        // "E-Sign Master": read, and switch off (an Update, as on the page). Never the picture.
        Route::prefix('e-signs')->name('e-signs.')->controller(ESignController::class)->group(function () {
            Route::get('/', 'index')->name('index')->middleware(ApiAccess::to('radio-management.e-sign'));
            Route::get('{signature}/uses', 'uses')->whereNumber('signature')->name('uses')->middleware(ApiAccess::to('radio-management.e-sign'));
            Route::post('{signature}/deactivate', 'deactivate')->whereNumber('signature')->name('deactivate')->middleware(ApiAccess::to('radio-management.e-sign', 'Update'));
        });

        // SharePoint: each folder and workbook of config('sharepoint-docs') with the menu of its page ("COF Scan", "ICT
        // Masterlist"), and the requests to change a cell ("Persetujuan Edit"). What is read from SharePoint: 30 a minute per
        // token; approving, which writes to it: 10. Asking for a change is an Update on the workbook's menu (PATCH on the page);
        // approving and turning down are an Update on "Persetujuan Edit", with a personal token only.
        Route::prefix('documents')->name('documents.')->middleware('throttle:api-v1-sharepoint')->group(function () {
            foreach (array_keys((array) config('sharepoint-docs.folders', [])) as $folder) {
                Route::prefix($folder)->name("{$folder}.")->controller(DocumentFolderController::class)->group(function () use ($folder) {
                    Route::get('/', 'index')->name('index')->defaults('folder', $folder)->middleware(ApiAccess::to("documents.{$folder}"));
                    Route::get('files/{id}', 'file')->where('id', '[^/]{1,200}')->name('file')->defaults('folder', $folder)->middleware(ApiAccess::to("documents.{$folder}"));
                });
            }

            foreach (array_keys((array) config('sharepoint-docs.workbooks', [])) as $workbook) {
                Route::prefix($workbook)->name("{$workbook}.")->controller(DocumentWorkbookController::class)->group(function () use ($workbook) {
                    Route::get('/', 'show')->name('show')->defaults('workbook', $workbook)->middleware(ApiAccess::to("documents.{$workbook}"));
                    Route::get('rows', 'rows')->name('rows')->defaults('workbook', $workbook)->middleware(ApiAccess::to("documents.{$workbook}"));
                    Route::post('edit-requests', 'requestEdit')->name('edit-requests.store')->defaults('workbook', $workbook)
                        ->middleware(ApiAccess::to("documents.{$workbook}", 'Update'));
                });
            }

            Route::prefix('edit-requests')->name('edit-requests.')->controller(EditRequestController::class)->group(function () {
                $approvals = 'documents.edit-approvals';
                Route::get('/', 'index')->name('index')->middleware(ApiAccess::to($approvals));
                Route::get('{editRequest}', 'show')->whereNumber('editRequest')->name('show')->middleware(ApiAccess::to($approvals));
                Route::post('{editRequest}/approve', 'approve')->whereNumber('editRequest')->name('approve')
                    ->middleware([PersonOnly::class, ApiAccess::to($approvals, 'Update'), 'throttle:api-v1-heavy']);
                Route::post('{editRequest}/reject', 'reject')->whereNumber('editRequest')->name('reject')
                    ->middleware([PersonOnly::class, ApiAccess::to($approvals, 'Update')]);
            });
        });
    });
