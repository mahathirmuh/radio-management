<?php

use Illuminate\Support\Facades\Route;
use Modules\AuditLog\Http\Controllers\AuditLogController;
use Modules\SharePointDocs\Http\Middleware\RequireMenu;

/*
| The audit trail (menu "Audit Log"). Read only: there is no route that changes or deletes an entry. The route names
| follow "<menu key>.<action>", so the `permission` middleware maps them to the menu: opening the page needs access to
| it, loading the entries needs Read. RequireMenu keeps the page closed to everyone but Super Admin while the menu is
| missing (the `permission` middleware alone would leave a route without a menu open to every account).
*/
Route::middleware(['web', 'auth', 'permission', RequireMenu::class])->prefix('audit-log')->name('audit-log.')
    ->controller(AuditLogController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
    });
