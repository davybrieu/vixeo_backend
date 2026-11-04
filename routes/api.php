<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogSearchController;
use App\Http\Controllers\Api\V1\CatalogTitleController;
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

        // Catalog (TMDb metadata, cached).
        Route::prefix('catalog')->name('catalog.')->middleware('throttle:catalog')->group(function () {
            Route::get('config', [CatalogController::class, 'config'])->name('config');
            Route::get('home', [CatalogController::class, 'home'])->name('home');
            Route::get('genres', [CatalogController::class, 'genres'])->name('genres');
            Route::get('search', [CatalogSearchController::class, 'search'])->name('search');
            Route::get('search/{type}', [CatalogSearchController::class, 'searchType'])->name('search.type');
            Route::get('{type}/lists/{list}', [CatalogController::class, 'list'])->name('list');
            Route::get('{type}/discover', [CatalogController::class, 'discover'])->name('discover');
            Route::get('movie/{id}', [CatalogTitleController::class, 'movie'])->whereNumber('id')->name('movie');
            Route::get('tv/{id}', [CatalogTitleController::class, 'show'])->whereNumber('id')->name('show');
            Route::get('tv/{id}/season/{season}', [CatalogTitleController::class, 'season'])
                ->whereNumber(['id', 'season'])->name('season');
            Route::get('person/{id}', [CatalogTitleController::class, 'person'])->whereNumber('id')->name('person');
        });
    });
});
