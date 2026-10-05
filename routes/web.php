<?php

use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use RealRashid\SweetAlert\Facades\Alert;
use App\Http\Controllers\Auth\LoginController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

//AUTHENTICATION
Alert::success('Welcome!');
// Accounts are created by an admin (User Management or `php artisan user:register`), never by
// the visitor: self-registration and password reset stay off.
Auth::routes(['register' => false, 'reset' => false, 'confirm' => false]);
Route::get('auth/google/redirect', [LoginController::class, 'redirectToProviderGoogle'])->name('sso-login');
Route::get('auth/google/callback', [LoginController::class, 'handleProviderCallbackGoogle'])->name('sso-callback');
Route::get('auth/microsoft/redirect', [LoginController::class, 'redirectToProviderMicrosoft'])->name('sso-login-mirosoft');
Route::get('auth/microsoft/callback', [LoginController::class, 'handleProviderCallbackMicrosoft'])->name('sso-callback-mirosoft');
// Not routed here: the "Merdeka Apps" gate (LoginController::redirectToApps, needs the mdka database)
// and the M-Safe employee-id login (nonCompanyEmailLogin, needs users.employee_id). Neither exists in this app.

/** Home: the sidebar logo and footer link here. Each user lands on the first page their role may open. */
Route::get('/', function () {
    return Auth::check() ? redirect()->route(Auth::user()->homeRoute()) : redirect()->route('login');
});

/** Dashboard */
require_once base_path('modules/Dashboard/Routes/web/dashboard.php');

/** User Management */
require_once base_path('modules/UserManagement/Routes/web/user-management.php');

/** Approval Management */
require_once base_path('modules/ApprovalManagement/Routes/web/approval-management.php');
