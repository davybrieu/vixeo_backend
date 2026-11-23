<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogSearchController;
use App\Http\Controllers\Api\V1\CatalogTitleController;
use App\Http\Controllers\Api\V1\DeviceLoginController;
use App\Http\Controllers\Api\V1\DirectMessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParentalControlController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileSearchController;
use App\Http\Controllers\Api\V1\ProfileSyncController;
use App\Http\Controllers\Api\V1\PushDeviceController;
use App\Http\Controllers\Api\V1\SocialProfileController;
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
        // TV sign-in with a code (docs/connexion-tv.md).
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
        // Channel logos, from our origin (App\Services\Tv\ChannelLogos). Public too: images are
        // loaded without a bearer token. The hash only points to a logo the API already listed.
        Route::get('logo/{hash}', [TvController::class, 'logo'])
            ->where('hash', '[0-9a-f]{32}')
            ->middleware('throttle:tv-logos')
            ->name('logo');
    });

    // Public social profile discovery (child profiles are excluded by the controller).
    Route::prefix('social')->name('social.')->middleware(['throttle:catalog', ResolveProfile::class])->group(function () {
        Route::get('search', [SocialProfileController::class, 'search'])->name('search');
        Route::get('profiles/{username}', [SocialProfileController::class, 'show'])->name('profiles.show');
        Route::get('profiles/{username}/followers', [SocialProfileController::class, 'followers'])->name('profiles.followers');
        Route::get('profiles/{username}/following', [SocialProfileController::class, 'following'])->name('profiles.following');
        Route::get('profiles/{username}/reviews', [SocialProfileController::class, 'reviews'])->name('profiles.reviews');
        Route::get('profiles/{username}/list', [SocialProfileController::class, 'favoritesList'])->name('profiles.list');
        Route::get('profiles/{username}/liked', [SocialProfileController::class, 'liked'])->name('profiles.liked');
        Route::get('profiles/{username}/people', [SocialProfileController::class, 'people'])->name('profiles.people');
    });

    // Public browsing and playback: no account or profile is required.
    // Personal data and profile actions remain inside auth:sanctum below.
    // Live TV, provider-neutral: credentials, mirrors and signatures stay server-side.
    Route::prefix('tv')->name('tv.')->middleware('throttle:catalog')->group(function () {
        Route::get('categories', [TvController::class, 'categories'])->name('categories');
        Route::get('channels', [TvController::class, 'channels'])->name('channels');
        // Channel ids are strings on some providers (Vavoo "tf1"), digits on Xtream.
        Route::get('channels/{id}', [TvController::class, 'show'])->name('channels.show');
        Route::get('channels/{id}/epg', [TvController::class, 'epg'])->name('channels.epg');
        Route::get('channels/{id}/stream', [TvController::class, 'stream'])->name('channels.stream');
    });

    // Catalog metadata, title search and playback helpers are public. A profile header,
    // when present, is still validated and used for age filtering.
    Route::prefix('catalog')->name('catalog.')->middleware(['throttle:catalog', ResolveProfile::class])->group(function () {
        Route::get('home', [CatalogController::class, 'home'])->name('home');
        Route::get('genres', [CatalogController::class, 'genres'])->name('genres');
        Route::get('titles', [CatalogController::class, 'titles'])->name('titles');
        Route::get('ratings', [CatalogController::class, 'ratings'])->middleware('throttle:catalog')->name('ratings');
        Route::get('search', [CatalogSearchController::class, 'search'])->name('search');
        Route::get('search/{type}', [CatalogSearchController::class, 'searchType'])->name('search.type');
        // The search page with filters only, no text (developer's request, 21/11/2026).
        Route::get('discover', [CatalogSearchController::class, 'discover'])->name('discover');
        Route::get('discover/{type}', [CatalogSearchController::class, 'discoverType'])
            ->whereIn('type', ['movie', 'tv'])->name('discover.type');
        Route::get('{type}/lists/{list}', [CatalogController::class, 'list'])->name('list');
        Route::get('movie/{id}', [CatalogTitleController::class, 'movie'])->whereNumber('id')->name('movie');
        Route::get('tv/{id}', [CatalogTitleController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('tv/{id}/season/{season}', [CatalogTitleController::class, 'season'])
            ->whereNumber(['id', 'season'])->name('season');
        Route::get('person/{id}', [CatalogTitleController::class, 'person'])->whereNumber('id')->name('person');
        // « Voir tout » of the person and title pages.
        Route::get('person/{id}/credits', [CatalogTitleController::class, 'personCredits'])
            ->whereNumber('id')->name('person.credits');
        Route::get('{type}/{id}/recommendations', [CatalogTitleController::class, 'recommendations'])
            ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('recommendations');
        Route::get('{type}/{id}/credits', [CatalogTitleController::class, 'credits'])
            ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('credits');

        // Player metadata: moments to skip and subtitles.
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
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        // Messaging between adult profiles (docs/messagerie.md); real time: routes/channels.php.
        Route::middleware([ResolveProfile::class, 'throttle:messages'])->prefix('messages')->name('messages.')->group(function () {
            Route::get('/', [DirectMessageController::class, 'index'])->name('index');
            Route::get('count', [DirectMessageController::class, 'count'])->name('count');
            Route::get('requests', [DirectMessageController::class, 'requests'])->name('requests');
            Route::post('with/{username}', [DirectMessageController::class, 'start'])->name('start');
            Route::post('groups', [DirectMessageController::class, 'createGroup'])->name('groups.store');
            Route::post('block/{username}', [DirectMessageController::class, 'block'])->name('block');
            Route::delete('block/{username}', [DirectMessageController::class, 'unblock'])->name('unblock');
            Route::get('attachments/{attachment}', [DirectMessageController::class, 'attachment'])->whereNumber('attachment')->name('attachments.show');
            Route::whereNumber('conversation')->group(function () {
                Route::get('{conversation}', [DirectMessageController::class, 'messages'])->name('show');
                Route::post('{conversation}', [DirectMessageController::class, 'send'])->name('send');
                Route::post('{conversation}/read', [DirectMessageController::class, 'read'])->name('read');
                Route::post('{conversation}/accept', [DirectMessageController::class, 'accept'])->name('accept');
                Route::delete('{conversation}/request', [DirectMessageController::class, 'decline'])->name('decline');
                Route::post('{conversation}/attachments', [DirectMessageController::class, 'attach'])->middleware('throttle:message-attachments')->name('attachments.store');
                Route::delete('{conversation}/{message}', [DirectMessageController::class, 'deleteMessage'])->whereNumber('message')->name('destroy');
                Route::put('{conversation}/group', [DirectMessageController::class, 'updateGroup'])->name('groups.update');
                Route::post('{conversation}/group/members', [DirectMessageController::class, 'addGroupMembers'])->name('groups.members.store');
                Route::delete('{conversation}/group/members/{username}', [DirectMessageController::class, 'removeGroupMember'])->name('groups.members.destroy');
                Route::post('{conversation}/group/leave', [DirectMessageController::class, 'leaveGroup'])->name('groups.leave');
                Route::post('{conversation}/group/admin/{username}', [DirectMessageController::class, 'transferGroupAdmin'])->name('groups.admin.transfer');
            });
        });
        Route::middleware(ResolveProfile::class)->prefix('social')->name('social.')->group(function () {
            Route::post('profiles/{username}/follow', [SocialProfileController::class, 'follow'])->name('profiles.follow');
            Route::delete('profiles/{username}/follow', [SocialProfileController::class, 'unfollow'])->name('profiles.unfollow');
            // Follow requests to a private account (docs/social.md).
            Route::get('requests', [SocialProfileController::class, 'requests'])->name('requests');
            Route::post('requests/{username}/accept', [SocialProfileController::class, 'acceptRequest'])->name('requests.accept');
            Route::delete('requests/{username}', [SocialProfileController::class, 'declineRequest'])->name('requests.decline');
            // « J'aime » on a member's review.
            Route::put('reviews/{review}/like', [SocialProfileController::class, 'likeReview'])->whereNumber('review')->name('reviews.like');
            Route::delete('reviews/{review}/like', [SocialProfileController::class, 'unlikeReview'])->whereNumber('review')->name('reviews.unlike');
        });
        // The app's « Notifications » page (docs/social.md).
        Route::middleware(ResolveProfile::class)->prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('count', [NotificationController::class, 'count'])->name('count');
            Route::post('read', [NotificationController::class, 'read'])->name('read');
            Route::get('episodes', [NotificationController::class, 'episodes'])->middleware('throttle:catalog')->name('episodes');
        });
        Route::prefix('auth/device')->name('auth.device.')->middleware('throttle:device-approve')->group(function () {
            Route::post('lookup', [DeviceLoginController::class, 'lookup'])->name('lookup');
            Route::post('approve', [DeviceLoginController::class, 'approve'])->name('approve');
        });
        Route::get('me', [AuthController::class, 'me'])->name('me');
        // Account data and devices (docs/compte.md).
        Route::prefix('me')->name('me.')->group(function () {
            Route::post('delete', [AccountController::class, 'destroy'])->middleware('throttle:account')->name('delete');
            Route::post('email', [AccountController::class, 'updateEmail'])->middleware('throttle:account')->name('email');
            Route::post('password', [AccountController::class, 'updatePassword'])->middleware('throttle:account')->name('password');
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

        // Push notifications of this app (docs/notifications.md).
        Route::prefix('push/device')->name('push.device.')->middleware(['throttle:push', ResolveProfile::class])->group(function () {
            Route::get('/', [PushDeviceController::class, 'show'])->name('show');
            Route::put('/', [PushDeviceController::class, 'update'])->name('update');
            Route::delete('/', [PushDeviceController::class, 'destroy'])->name('destroy');
            Route::post('test', [PushDeviceController::class, 'test'])->name('test');
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

            // Recent searches, the same on all the profile's devices (docs/lecture.md).
            Route::get('searches', [ProfileSearchController::class, 'index'])->name('searches.index');
            Route::post('searches', [ProfileSearchController::class, 'store'])->name('searches.store');
            Route::post('searches/clear', [ProfileSearchController::class, 'clear'])->name('searches.clear');
            Route::delete('searches/{search}', [ProfileSearchController::class, 'destroy'])
                ->scopeBindings()->whereNumber('search')->name('searches.destroy');
        });

        // The profile's reviews (docs/avis.md), under their historical address.
        Route::prefix('profiles/{profile}/tmdb')->name('profiles.tmdb.')->middleware('throttle:reviews')->group(function () {
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
    });
});
