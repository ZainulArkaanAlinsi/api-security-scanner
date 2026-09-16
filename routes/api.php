<?php

use App\Http\Controllers\Api\ScanApiController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

/*
 * Token-authenticated API for CI pipelines.
 *   curl -H "Authorization: Bearer apisc_..." https://host/api/v1/tickets
 */
Route::middleware([AuthenticateApiToken::class, 'throttle:60,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('/tickets', [ScanApiController::class, 'index']);
        Route::post('/scans', [ScanApiController::class, 'store']);
        Route::get('/scans/{ticket}', [ScanApiController::class, 'show']);
    });
