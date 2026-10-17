<?php

use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\ApiTokenController;
use Modules\SharePointDocs\Http\Middleware\RequireMenu;

/*
| The page "API Tokens" (section Settings). The route names follow "<menu key>.<action>", so the `permission` middleware
| maps them to the menu: opening the page needs access to it, loading the list Read, making a token or a client Create,
| changing a client Update, revoking a token or deleting a client Delete. The API clients and other people's tokens are
| Super Admin's (the controller checks). RequireMenu keeps the page closed while its menu is missing.
*/
Route::middleware(['web', 'auth', 'permission', RequireMenu::class])->prefix('settings/api-tokens')->name('api-tokens.')
    ->controller(ApiTokenController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('list', 'list')->name('list');
        Route::post('tokens', 'storeToken')->name('store-token');
        Route::delete('tokens/{token}', 'destroyToken')->whereNumber('token')->name('destroy-token');
        Route::post('clients', 'storeClient')->name('store-client');
        Route::put('clients/{client}', 'updateClient')->whereNumber('client')->name('update-client');
        Route::delete('clients/{client}', 'destroyClient')->whereNumber('client')->name('destroy-client');
    });
