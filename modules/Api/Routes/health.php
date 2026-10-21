<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;
use Modules\Api\Http\Controllers\HealthController;
use Modules\Api\Http\Middleware\ForceJsonResponse;
use Modules\Api\Http\Middleware\LimitAddresses;

/*
| Whether the application is up (HealthController).
|
| /up: the application answers, for the health check of the container (docker-compose.yml). No middleware group: no
| session, no database, no token.
|
| /api/v1/health: the database and the storage without a token; everything the application depends on with one (any token
| of the API, not a permission of a menu). Like the rest of the API: JSON, the client's language, 120 requests a minute per
| address. Not the API's own checks (ApiReady, auth:sanctum): the answer has to come when the database does not.
*/
Route::get('up', [HealthController::class, 'up'])->name('health.up');

Route::get('api/v1/health', [HealthController::class, 'show'])->name('api.v1.health')
    ->middleware([SetLocale::class . ':api', ForceJsonResponse::class, LimitAddresses::class]);
