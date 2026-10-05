<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalManagement\Http\Controllers\ApprovalManagementController;

Route::middleware(['web', 'auth', 'permission'])->prefix('approval-management')->name('approval-management.')->group(function () {
    Route::prefix('flows')->name('approval-flows.')->group(function () {
        Route::get('/', [ApprovalManagementController::class, 'indexApprovalFlows'])->name('index');
    });

    Route::prefix('inbox')->name('approval-inbox.')->group(function () {
        Route::get('/', [ApprovalManagementController::class, 'indexInbox'])->name('index');
    });

    Route::prefix('delegate')->name('approval-delegate.')->group(function () {
        Route::get('/', [ApprovalManagementController::class, 'indexApprovalDelegate'])->name('index');
    });

    Route::prefix('takeover')->name('approval-takeover.')->group(function () {
        Route::get('/', [ApprovalManagementController::class, 'indexApprovalTakeover'])->name('index');
    });
});