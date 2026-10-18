<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\RolePermissionsController;
use Modules\UserManagement\Http\Controllers\UserRegisteredController;

Route::middleware(['web', 'auth', 'permission'])->prefix('user-management')->name('user-management.')->group(function () {
    Route::prefix('user-registered')->name('user-registered.')->group(function () {
            Route::get('/', [UserRegisteredController::class, 'index'])->name('index');
            Route::get('list', [UserRegisteredController::class, 'list'])->name('list');
            // "Find employee" of the form. Held back so the page cannot be used to read the whole directory out.
            Route::get('employees', [UserRegisteredController::class, 'employees'])->name('employees')->middleware('throttle:60,1');
            // The same, in Active Directory (with LDAP chosen), held back the same way.
            Route::get('directory', [UserRegisteredController::class, 'directory'])->name('directory')->middleware('throttle:60,1');
            // The accounts ticked in the list. Before "/{id}", which would take "bulk" for an id; named "bulk-..." (not
            // "bulk.") so the `permission` middleware finds the menu: the status is an Update, deleting a Delete.
            Route::put('bulk/status', [UserRegisteredController::class, 'bulkStatus'])->name('bulk-status');
            Route::delete('bulk', [UserRegisteredController::class, 'bulkDestroy'])->name('bulk-destroy');
            Route::get('/{id}', [UserRegisteredController::class, 'show'])->name('show');
            Route::post('/', [UserRegisteredController::class, 'store'])->name('store');
            Route::put('/{id}', [UserRegisteredController::class, 'update'])->name('update');
            Route::put('/{id}/status', [UserRegisteredController::class, 'status'])->name('status');
            Route::put('/{id}/password', [UserRegisteredController::class, 'password'])->name('password');
            Route::delete('/{id}', [UserRegisteredController::class, 'destroy'])->name('destroy');
        });
        Route::get('role-permission/list', [RolePermissionsController::class, 'list'])->name('role-permission.list');
        Route::resource('role-permission', RolePermissionsController::class);
    });
