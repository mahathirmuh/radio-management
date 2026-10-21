<?php

use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LanguageController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SessionController;


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
// Accounts are created by an admin (User Management or `php artisan user:register`), never by
// the visitor: self-registration and password reset stay off.
Auth::routes(['register' => false, 'reset' => false, 'confirm' => false]);
Route::get('auth/microsoft/redirect', [LoginController::class, 'redirectToProviderMicrosoft'])->name('sso-login-mirosoft');
Route::get('auth/microsoft/callback', [LoginController::class, 'handleProviderCallbackMicrosoft'])->name('sso-callback-mirosoft');
// Not routed here: the "Merdeka Apps" gate (LoginController::redirectToApps, needs the mdka database)
// and the M-Safe employee-id login (nonCompanyEmailLogin, needs users.employee_id). Neither exists in this app.

/** The clock of a signed-in session (EndExpiredSession): what is left of it, and "stay signed in". No menu: everyone signed in has one. */
Route::middleware('auth')->prefix('session')->name('session.')->controller(SessionController::class)->group(function () {
    Route::get('status', 'status')->name('status');
    Route::post('keep-alive', 'keepAlive')->name('keep-alive');
});

/** The language of the screens (account menu, sign-in page): no menu and no sign-in needed, the sign-in page has it too. */
Route::post('language', LanguageController::class)->name('language.switch');

/** What changed in each version (CHANGELOG.md): the version in the footer opens it. No menu: every page has the footer. */
Route::get('changelog', ChangelogController::class)->middleware('auth')->name('changelog');

/** Home: the sidebar logo links here. Each user lands on the first page their role may open. */
Route::get('/', function () {
    return Auth::check() ? redirect()->route(Auth::user()->homeRoute()) : redirect()->route('login');
});

/** Dashboard */
require_once base_path('modules/Dashboard/Routes/web/dashboard.php');

/** User Management */
require_once base_path('modules/UserManagement/Routes/web/user-management.php');

/** Approval Management */
require_once base_path('modules/ApprovalManagement/Routes/web/approval-management.php');
