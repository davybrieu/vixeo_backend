<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogSearchController;
use App\Http\Controllers\Api\V1\CatalogTitleController;
use App\Http\Controllers\Api\V1\DeviceLoginController;
use App\Http\Controllers\Api\V1\ParentalControlController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileLibraryController;
use App\Http\Controllers\Api\V1\ProfileSyncController;
use App\Http\Middleware\ResolveProfile;
use Illuminate\Support\Facades\Route;

/*
| API v1. Installed mobile apps don't update in lockstep with the API:
| never make a breaking change here, add a v2 instead.
*/
Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        // TV sign-in with a QR code (docs/connexion-tv.md).
        Route::post('device', [DeviceLoginController::class, 'start'])->name('device.start');
    });
    Route::post('auth/device/token', [DeviceLoginController::class, 'poll'])
        ->middleware('throttle:device-poll')
        ->name('auth.device.poll');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::prefix('auth/device')->name('auth.device.')->middleware('throttle:device-approve')->group(function () {
            Route::post('lookup', [DeviceLoginController::class, 'lookup'])->name('lookup');
            Route::post('approve', [DeviceLoginController::class, 'approve'])->name('approve');
        });
        Route::get('me', [AuthController::class, 'me'])->name('me');

        // Wrong parental PINs are also limited by App\Services\ParentalControl (5 per 5 min).
        Route::prefix('parental-control')->name('parental-control.')->middleware('throttle:parental')->group(function () {
            Route::get('/', [ParentalControlController::class, 'show'])->name('show');
            Route::put('pin', [ParentalControlController::class, 'update'])->name('pin.update');
            // POST, not DELETE: the PIN / password must travel in the body, never in the URL.
            Route::post('disable', [ParentalControlController::class, 'destroy'])->name('disable');
            Route::post('verify', [ParentalControlController::class, 'verify'])->name('verify');
        });

        Route::get('avatars', [AvatarController::class, 'index'])->name('avatars');
        Route::apiResource('profiles', ProfileController::class);
        Route::post('profiles/{profile}/verify-pin', [ProfileController::class, 'verifyPin'])
            ->middleware('throttle:pin')
            ->name('profiles.verify-pin');

        // Per-profile sync (favorites, progress, watched): see docs/synchronisation.md.
        Route::prefix('profiles/{profile}')->name('profiles.')->middleware('throttle:sync')->group(function () {
            Route::get('sync', [ProfileSyncController::class, 'pull'])->name('sync.pull');
            Route::post('sync', [ProfileSyncController::class, 'push'])->name('sync.push');
            Route::get('continue-watching', [ProfileLibraryController::class, 'continueWatching'])->name('continue-watching');
            Route::get('favorites', [ProfileLibraryController::class, 'favorites'])->name('favorites');
            Route::get('history', [ProfileLibraryController::class, 'history'])->name('history');
        });

        // Catalog (TMDb metadata, cached).
        Route::prefix('catalog')->name('catalog.')->middleware(['throttle:catalog', ResolveProfile::class])->group(function () {
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
