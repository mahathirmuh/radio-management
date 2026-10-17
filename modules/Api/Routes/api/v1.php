<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\V1\DashboardController;
use Modules\Api\Http\Controllers\V1\HistoryController;
use Modules\Api\Http\Controllers\V1\MasterDataController;
use Modules\Api\Http\Controllers\V1\MeController;
use Modules\Api\Http\Controllers\V1\RadioController;
use Modules\Api\Http\Controllers\V1\ReferenceController;
use Modules\Api\Http\Middleware\ApiAccess;
use Modules\Api\Http\Middleware\ApiReady;
use Modules\Api\Http\Middleware\EnsureApiAccount;
use Modules\Api\Http\Middleware\ForceJsonResponse;
use Modules\Api\Http\Middleware\LimitAddresses;

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
    });
