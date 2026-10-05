<?php

use Illuminate\Support\Facades\Route;
use Modules\Dashboard\Http\Controllers\DashboardController;

Route::middleware(['web', 'auth', 'permission'])->group(function () {
    Route::resource('dashboard', DashboardController::class);
});
