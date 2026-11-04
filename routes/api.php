<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| API v1. Installed mobile apps don't update in lockstep with the API:
| never make a breaking change here, add a v2 instead.
*/
Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::apiResource('profiles', ProfileController::class);
        Route::post('profiles/{profile}/verify-pin', [ProfileController::class, 'verifyPin'])
            ->middleware('throttle:pin')
            ->name('profiles.verify-pin');
    });
});
