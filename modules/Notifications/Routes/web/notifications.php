<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;

/*
| The notifications of whoever is signed in (the bell in the header, and "See all"). No menu: every person has their own,
| and only ever sees those (each action looks for the notification among the person's own).
*/
Route::middleware(['web', 'auth'])->prefix('notifications')->name('notifications.')
    ->controller(NotificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('summary', 'summary')->name('summary');
        Route::get('{notification}/open', 'open')->whereNumber('notification')->name('open');
        Route::post('{notification}/read', 'read')->whereNumber('notification')->name('read');
        Route::post('read-all', 'readAll')->name('read-all');
    });
