<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogSearchController;
use App\Http\Controllers\Api\V1\CatalogTitleController;
use App\Http\Controllers\Api\V1\DeadStreamController;
use App\Http\Controllers\Api\V1\DeviceLoginController;
use App\Http\Controllers\Api\V1\ParentalControlController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileLibraryController;
use App\Http\Controllers\Api\V1\ProfileSearchController;
use App\Http\Controllers\Api\V1\ProfileSyncController;
use App\Http\Controllers\Api\V1\StreamController;
use App\Http\Controllers\Api\V1\StreamPreferenceController;
use App\Http\Controllers\Api\V1\TmdbAccountController;
use App\Http\Controllers\Api\V1\TmdbRatingController;
use App\Http\Controllers\Api\V1\TvController;
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
        // "Mot de passe oublié" (docs/compte.md).
        Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
        Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
    });
    // Installed Android / iOS apps ask whether to update (docs/mise-a-jour.md).
    Route::get('app/update', [AppUpdateController::class, 'check'])
        ->middleware('throttle:app-update')
        ->name('app.update');
    Route::post('auth/device/token', [DeviceLoginController::class, 'poll'])
        ->middleware('throttle:device-poll')
        ->name('auth.device.poll');

    // Media proxied through our own origin: the player loads this URL without a bearer token
    // (a <video> element and ExoPlayer send none), so the signed, opaque token in the path is what
    // authorises it. Public on purpose: kept out of the `auth:sanctum` group and of
    // `throttle:catalog` (a live stream fires one request per segment). See docs/tv-direct.md.
    Route::prefix('tv')->name('tv.')->group(function () {
        Route::get('stream/{token}', [TvController::class, 'media'])->name('stream.media');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::prefix('auth/device')->name('auth.device.')->middleware('throttle:device-approve')->group(function () {
            Route::post('lookup', [DeviceLoginController::class, 'lookup'])->name('lookup');
            Route::post('approve', [DeviceLoginController::class, 'approve'])->name('approve');
        });
        Route::get('me', [AuthController::class, 'me'])->name('me');
        // Account data and devices (docs/compte.md).
        Route::prefix('me')->name('me.')->group(function () {
            Route::post('delete', [AccountController::class, 'destroy'])->middleware('throttle:account')->name('delete');
            Route::post('export', [AccountController::class, 'export'])->middleware('throttle:account-export')->name('export');
            Route::get('devices', [AccountController::class, 'devices'])->name('devices');
            Route::delete('devices/{device}', [AccountController::class, 'revokeDevice'])->whereNumber('device')->name('devices.revoke');
        });

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

            // Stream preferences of a content: favorites (❤) and the last stream that worked.
            Route::get('streams', [StreamPreferenceController::class, 'index'])->name('streams.index');
            Route::post('streams/favorite', [StreamPreferenceController::class, 'favorite'])->name('streams.favorite');
            Route::post('streams/worked', [StreamPreferenceController::class, 'worked'])->name('streams.worked');

            // Recent searches, the same on all the profile's devices (docs/lecture.md).
            Route::get('searches', [ProfileSearchController::class, 'index'])->name('searches.index');
            Route::post('searches', [ProfileSearchController::class, 'store'])->name('searches.store');
            Route::post('searches/clear', [ProfileSearchController::class, 'clear'])->name('searches.clear');
            Route::delete('searches/{search}', [ProfileSearchController::class, 'destroy'])
                ->scopeBindings()->whereNumber('search')->name('searches.destroy');
        });

        // TMDb account of a profile and its ratings (docs/compte-tmdb.md).
        Route::prefix('profiles/{profile}/tmdb')->name('profiles.tmdb.')->middleware('throttle:tmdb-account')->group(function () {
            Route::get('/', [TmdbAccountController::class, 'show'])->name('show');
            Route::delete('/', [TmdbAccountController::class, 'destroy'])->name('destroy');
            Route::post('link', [TmdbAccountController::class, 'start'])->name('link');
            Route::post('link/complete', [TmdbAccountController::class, 'complete'])->name('link.complete');
            Route::get('ratings', [TmdbRatingController::class, 'index'])->name('ratings.index');
            Route::get('ratings/tv/{id}/season/{season}', [TmdbRatingController::class, 'season'])
                ->whereNumber(['id', 'season'])->name('ratings.season');
            Route::put('ratings/tv/{id}/season/{season}/episode/{episode}', [TmdbRatingController::class, 'rateEpisode'])
                ->whereNumber(['id', 'season', 'episode'])->name('ratings.episode.rate');
            Route::delete('ratings/tv/{id}/season/{season}/episode/{episode}', [TmdbRatingController::class, 'unrateEpisode'])
                ->whereNumber(['id', 'season', 'episode'])->name('ratings.episode.unrate');
            Route::get('ratings/{type}/{id}', [TmdbRatingController::class, 'show'])
                ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('ratings.show');
            Route::put('ratings/{type}/{id}', [TmdbRatingController::class, 'rate'])
                ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('ratings.rate');
            Route::delete('ratings/{type}/{id}', [TmdbRatingController::class, 'unrate'])
                ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('ratings.unrate');
        });

        // Live TV, provider-neutral: the app calls /tv/… and never knows the provider
        // (Vavoo, a personal Xtream panel…). Credentials, mirrors and signatures stay server-side.
        Route::prefix('tv')->name('tv.')->middleware('throttle:catalog')->group(function () {
            Route::get('categories', [TvController::class, 'categories'])->name('categories');
            Route::get('channels', [TvController::class, 'channels'])->name('channels');
            // Channel ids are strings on some providers (Vavoo "tf1"), digits on Xtream.
            Route::get('channels/{id}', [TvController::class, 'show'])->name('channels.show');
            Route::get('channels/{id}/stream', [TvController::class, 'stream'])->name('channels.stream');
        });

        // Catalog (TMDb metadata, cached).
        Route::prefix('catalog')->name('catalog.')->middleware(['throttle:catalog', ResolveProfile::class])->group(function () {
            Route::get('config', [CatalogController::class, 'config'])->name('config');
            Route::get('home', [CatalogController::class, 'home'])->name('home');
            Route::get('genres', [CatalogController::class, 'genres'])->name('genres');
            Route::get('titles', [CatalogController::class, 'titles'])->name('titles');
            Route::get('search', [CatalogSearchController::class, 'search'])->name('search');
            Route::get('search/{type}', [CatalogSearchController::class, 'searchType'])->name('search.type');
            Route::get('people/popular', [CatalogSearchController::class, 'popularPeople'])->name('people.popular');
            Route::get('{type}/lists/{list}', [CatalogController::class, 'list'])->name('list');
            Route::get('{type}/discover', [CatalogController::class, 'discover'])->name('discover');
            Route::get('movie/{id}', [CatalogTitleController::class, 'movie'])->whereNumber('id')->name('movie');
            Route::get('tv/{id}', [CatalogTitleController::class, 'show'])->whereNumber('id')->name('show');
            Route::get('tv/{id}/season/{season}', [CatalogTitleController::class, 'season'])
                ->whereNumber(['id', 'season'])->name('season');
            Route::get('person/{id}', [CatalogTitleController::class, 'person'])->whereNumber('id')->name('person');

            // Player: moments to skip (TheIntroDB) and subtitles (OpenSubtitles), docs/lecture.md.
            Route::get('movie/{id}/segments', [PlaybackController::class, 'movieSegments'])
                ->whereNumber('id')->name('movie.segments');
            Route::get('tv/{id}/season/{season}/episode/{episode}/segments', [PlaybackController::class, 'episodeSegments'])
                ->whereNumber(['id', 'season', 'episode'])->name('episode.segments');
            Route::get('movie/{id}/subtitles', [PlaybackController::class, 'movieSubtitles'])
                ->whereNumber('id')->name('movie.subtitles');
            Route::get('tv/{id}/season/{season}/episode/{episode}/subtitles', [PlaybackController::class, 'episodeSubtitles'])
                ->whereNumber(['id', 'season', 'episode'])->name('episode.subtitles');
            Route::get('subtitles/{file}', [PlaybackController::class, 'subtitleFile'])
                ->whereNumber('file')->middleware('throttle:subtitles')->name('subtitles.file');

            // Stream sources (FrenchStream, Wiflix, Frembed via Node scraper).
            Route::get('sources/providers', [StreamController::class, 'providers'])->name('sources.providers');
            Route::get('movie/{id}/sources', [StreamController::class, 'movieSources'])
                ->whereNumber('id')->name('movie.sources');
            Route::get('tv/{id}/season/{season}/episode/{episode}/sources', [StreamController::class, 'episodeSources'])
                ->whereNumber(['id', 'season', 'episode'])->name('episode.sources');
            // Resolve a chosen player link into a playable stream (docs/lecture.md).
            Route::post('resolve', [StreamController::class, 'resolve'])
                ->middleware('throttle:resolve')->name('resolve');

            // Report a stream as dead, so the sources panel drops it for everyone (24 h).
            Route::post('streams/dead', [DeadStreamController::class, 'store'])->name('streams.dead');
        });
    });
});
