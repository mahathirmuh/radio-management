<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth')->name('api.')->group(function () {
    // `require`, not `require_once`: every application instance (e.g. each test) must get these routes.
    require base_path('modules/ApprovalManagement/Routes/api/approval-management.php');
});


