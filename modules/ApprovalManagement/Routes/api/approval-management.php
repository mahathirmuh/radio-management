<?php

use Illuminate\Support\Facades\Route;
use Modules\ApprovalManagement\Http\Controllers\ApiApprovalInboxController;
use Modules\ApprovalManagement\Http\Controllers\ApiApprovalManagementController;

Route::prefix('approval-management')->name('approval-management.')->group(function () {
    Route::prefix('flows')->name('flows.')->group(function () {
        Route::get('datatable', [ApiApprovalManagementController::class, 'datatableFlows'])->name('datatable');
        Route::post('/', [ApiApprovalManagementController::class, 'flowStore'])->name('store');
        Route::get('{id}', [ApiApprovalManagementController::class, 'flowShow'])->name('show');
        Route::put('{id}', [ApiApprovalManagementController::class, 'flowUpdate'])->name('update');
        Route::delete('{id}', [ApiApprovalManagementController::class, 'flowDestroy'])->name('destroy');
    });

    Route::prefix('stages')->name('stages.')->group(function () {
        Route::get('datatable', [ApiApprovalManagementController::class, 'stageDatatable'])->name('datatable');
        Route::post('/', [ApiApprovalManagementController::class, 'stageStore'])->name('store');
        Route::get('{id}', [ApiApprovalManagementController::class, 'stageShow'])->name('show');
        Route::put('{id}', [ApiApprovalManagementController::class, 'stageUpdate'])->name('update');
        Route::delete('{id}', [ApiApprovalManagementController::class, 'stageDestroy'])->name('destroy');
    });

    Route::prefix('steps')->name('steps.')->group(function () {
        Route::get('datatable', [ApiApprovalManagementController::class, 'stepDatatable'])->name('datatable');
        Route::post('/', [ApiApprovalManagementController::class, 'stepStore'])->name('store');
        Route::get('{id}', [ApiApprovalManagementController::class, 'show'])->name('show');
        Route::put('{id}', [ApiApprovalManagementController::class, 'stepUpdate'])->name('update');
        Route::delete('{id}', [ApiApprovalManagementController::class, 'stepDestroy'])->name('destroy');
    });

    Route::prefix('inbox')->name('inbox.')->group(function () {
        Route::get('datatable', [ApiApprovalInboxController::class, 'datatable'])->name('datatable');
        Route::get('{id}', [ApiApprovalInboxController::class, 'show'])->name('show');
        Route::post('{id}/act', [ApiApprovalInboxController::class, 'act'])->name('act');
        Route::post('{id}/insert-step', [ApiApprovalInboxController::class, 'insertStep'])->name('insert-step');
        Route::post('{id}/delegate', [ApiApprovalInboxController::class, 'delegate'])->name('delegate');
        Route::post('{id}/takeover', [ApiApprovalInboxController::class, 'takeover'])->name('takeover');
    });

    Route::prefix('delegate')->name('delegate.')->group(function () {
        Route::get('datatable', [ApiApprovalManagementController::class, 'datatableDelegate'])->name('datatable');
    });

    Route::prefix('takeover')->name('takeover.')->group(function () {
        Route::get('datatable', [ApiApprovalManagementController::class, 'datatableTakeover'])->name('datatable');
    });
});
