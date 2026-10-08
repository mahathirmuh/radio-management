<?php

use Illuminate\Support\Facades\Route;
use Modules\RadioManagement\Http\Controllers\MasterDataController;
use Modules\RadioManagement\Http\Controllers\RadioController;
use Modules\RadioManagement\Http\Controllers\RadioHistoryController;
use Modules\RadioManagement\Http\Controllers\RadioImportController;

/*
| Route names follow "<menu key>.<action>" so the `permission` middleware can map
| every request to its menu (e.g. radio-management.radio.list -> Register Radio).
*/
Route::middleware(['web', 'auth', 'permission'])->prefix('radio-management')->name('radio-management.')->group(function () {
    Route::prefix('radio')->name('radio.')->controller(RadioController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
        Route::get('next-id', 'nextId')->name('next-id');
        Route::get('export', 'export')->name('export');
        Route::get('{radio}', 'show')->name('show')->whereNumber('radio');
        Route::get('{radio}/detail', 'detail')->name('detail')->whereNumber('radio');
        // The IT Equipment Checkout Form of one radio, filled in from the register (shown in the print pop-up).
        Route::get('{radio}/checkout-form', 'checkoutForm')->name('checkout-form')->where('radio', '[0-9]{1,15}');
        Route::post('/', 'store')->name('store');
        Route::put('{radio}', 'update')->name('update')->whereNumber('radio');
        Route::put('{radio}/archive', 'archive')->name('archive')->whereNumber('radio');
        Route::put('{radio}/restore', 'restore')->name('restore')->whereNumber('radio');
        // PUT, not POST: a history line changes an existing radio (Update permission).
        Route::put('{radio}/history', 'storeHistory')->name('add-history')->whereNumber('radio');
        Route::delete('{radio}', 'destroy')->name('destroy')->whereNumber('radio');
    });

    Route::prefix('history')->name('history.')->controller(RadioHistoryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
    });

    foreach (MasterDataController::KEYS as $master) {
        Route::prefix("master/{$master}")->name("{$master}.")->controller(MasterDataController::class)
            ->group(function () use ($master) {
                Route::get('/', 'index')->name('index')->defaults('master', $master);
                Route::get('list', 'list')->name('list')->defaults('master', $master);
                Route::get('{id}', 'show')->name('show')->whereNumber('id')->defaults('master', $master);
                Route::post('/', 'store')->name('store')->defaults('master', $master);
                Route::put('{id}', 'update')->name('update')->whereNumber('id')->defaults('master', $master);
                Route::delete('{id}', 'destroy')->name('destroy')->whereNumber('id')->defaults('master', $master);
            });
    }

    Route::prefix('import')->name('import.')->controller(RadioImportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
    });
});
