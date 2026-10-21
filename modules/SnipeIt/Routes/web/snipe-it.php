<?php

use Illuminate\Support\Facades\Route;
use Modules\SharePointDocs\Http\Middleware\RequireMenu;
use Modules\SnipeIt\Http\Controllers\SnipeItController;
use Modules\SnipeIt\Services\Lists;

/*
| The menu "Snipe-IT": what is in Snipe-IT, read only (there is no route that changes anything, here or there). Each page is
| an entry of the sidebar: the route names follow "<menu key>.<action>", so the `permission` middleware maps them to it
| (opening a page needs access to it, loading its rows needs Read). RequireMenu keeps the pages closed to everyone but
| Super Admin while the menu is missing.
*/
Route::middleware(['web', 'auth', 'permission', RequireMenu::class])->prefix('snipe-it')->name('snipe-it.')
    ->controller(SnipeItController::class)->group(function () {
        Route::get('/', 'overview')->name('overview.index');
        foreach (array_keys(Lists::GROUPS) as $group) {
            Route::get($group, 'index')->name("{$group}.index")->defaults('group', $group);
            Route::get("{$group}/list", 'list')->name("{$group}.list")->defaults('group', $group);
        }
        // The detail of one asset (a page of the entry "Aset": needs Read on it).
        Route::get('assets/{asset}', 'asset')->whereNumber('asset')->name('assets.show');
    });
