<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\RolePermissionsController;
use Modules\UserManagement\Http\Controllers\UserRegisteredController;

Route::middleware(['web', 'auth', 'permission'])->prefix('user-management')->name('user-management.')->group(function () {
    Route::prefix('user-registered')->name('user-registered.')->group(function () {
            Route::get('/', [UserRegisteredController::class, 'index'])->name('index');
            Route::get('list', [UserRegisteredController::class, 'list'])->name('list');
            Route::get('/{id}', [UserRegisteredController::class, 'show'])->name('show');
            Route::post('/', [UserRegisteredController::class, 'store'])->name('store');
            Route::put('/{id}', [UserRegisteredController::class, 'update'])->name('update');
            Route::delete('/{id}', [UserRegisteredController::class, 'destroy'])->name('destroy');
        });
        Route::get('role-permission/list', [RolePermissionsController::class, 'list'])->name('role-permission.list');
        Route::resource('role-permission', RolePermissionsController::class);
    });
