<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AppUpdateController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CatalogSearchController;
use App\Http\Controllers\Api\V1\CatalogTitleController;
use App\Http\Controllers\Api\V1\CoverController;
use App\Http\Controllers\Api\V1\DeviceLoginController;
use App\Http\Controllers\Api\V1\DiaryController;
use App\Http\Controllers\Api\V1\DirectMessageController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\ListController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ParentalControlController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PlaybackController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfileCoverController;
use App\Http\Controllers\Api\V1\ProfilePhotoController;
use App\Http\Controllers\Api\V1\ProfileSearchController;
use App\Http\Controllers\Api\V1\ProfileSyncController;
use App\Http\Controllers\Api\V1\PushDeviceController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReviewSocialController;
use App\Http\Controllers\Api\V1\SocialLoginController;
use App\Http\Controllers\Api\V1\SocialProfileController;
use App\Http\Controllers\Api\V1\TitleArtworkController;
use App\Http\Controllers\Api\V1\TitleSocialController;
use App\Http\Controllers\Api\V1\TmdbRatingController;
use App\Http\Controllers\Api\V1\TvController;
use App\Http\Controllers\Api\V1\WeeklyReleasesController;
use App\Http\Middleware\ResolveProfile;
use App\Services\ProfileCovers;
use App\Services\ProfilePhotos;
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
        // « Continuer avec Google / Apple » (docs/connexion-sociale.md): the sign-in runs in a
        // browser, which comes back to the API (callback), then to the app with a code.
        Route::get('social', [SocialLoginController::class, 'index'])->name('social.index');
        Route::post('social/token', [SocialLoginController::class, 'token'])->name('social.token');
        Route::get('social/{provider}', [SocialLoginController::class, 'start'])
            ->whereIn('provider', ['google', 'apple'])->name('social.start');
        Route::match(['get', 'post'], 'social/{provider}/callback', [SocialLoginController::class, 'callback'])
            ->whereIn('provider', ['google', 'apple'])->name('social.callback');
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
    // Profile photos (App\Services\ProfilePhotos): public, loaded without a bearer token like any
    // image; the random name changes with each photo.
    Route::get('profile-photos/{name}', [ProfilePhotoController::class, 'show'])
        ->where('name', ProfilePhotos::NAME_PATTERN)
        ->middleware('throttle:profile-photos')
        ->name('profile-photos.show');
    // Profile covers (App\Services\ProfileCovers): the same.
    Route::get('profile-covers/{name}', [ProfileCoverController::class, 'show'])
        ->where('name', ProfileCovers::NAME_PATTERN)
        ->middleware('throttle:profile-photos')
        ->name('profile-covers.show');

    Route::prefix('tv')->name('tv.')->group(function () {
        Route::get('stream/{token}', [TvController::class, 'media'])->name('stream.media');
        // Channel logos, from our origin (App\Services\Tv\ChannelLogos). Public too: images are
        // loaded without a bearer token. The hash only points to a logo the API already listed.
        Route::get('logo/{hash}', [TvController::class, 'logo'])
            ->where('hash', '[0-9a-f]{32}')
            ->middleware('throttle:tv-logos')
            ->name('logo');
    });

    // Social profile discovery requires an authenticated account (child profiles are excluded by the controller).
    Route::prefix('social')->name('social.')->middleware(['auth:sanctum', 'throttle:catalog', ResolveProfile::class])->group(function () {
        Route::get('search', [SocialProfileController::class, 'search'])->name('search');
        Route::get('profiles/{username}', [SocialProfileController::class, 'show'])->name('profiles.show');
        Route::get('profiles/{username}/followers', [SocialProfileController::class, 'followers'])->name('profiles.followers');
        Route::get('profiles/{username}/following', [SocialProfileController::class, 'following'])->name('profiles.following');
        Route::get('profiles/{username}/reviews', [SocialProfileController::class, 'reviews'])->name('profiles.reviews');
        Route::get('profiles/{username}/list', [SocialProfileController::class, 'favoritesList'])->name('profiles.list');
        Route::get('profiles/{username}/liked', [SocialProfileController::class, 'liked'])->name('profiles.liked');
        Route::get('profiles/{username}/watched', [SocialProfileController::class, 'watched'])->name('profiles.watched');
        // The social network of movies and TV shows (docs/social.md): activity, lists, the « Bilan
        // de l'année », reviews and their comments, the social part of a title page.
        Route::get('profiles/{username}/activity', [FeedController::class, 'activity'])->name('profiles.activity');
        Route::get('profiles/{username}/lists', [SocialProfileController::class, 'lists'])->name('profiles.lists');
        Route::get('profiles/{username}/stats', [DiaryController::class, 'stats'])->name('profiles.stats');
        Route::get('reviews/{review}', [ReviewSocialController::class, 'show'])->whereNumber('review')->name('reviews.show');
        Route::get('reviews/{review}/comments', [ReviewSocialController::class, 'comments'])->whereNumber('review')->name('reviews.comments');
        Route::get('titles/{type}/{id}', [TitleSocialController::class, 'show'])->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('titles.show');
        Route::get('titles/{type}/{id}/reviews', [TitleSocialController::class, 'reviews'])->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('titles.reviews');
        Route::get('lists/{list}', [ListController::class, 'show'])->whereNumber('list')->name('lists.show');
        Route::get('lists/{list}/comments', [ListController::class, 'comments'])->whereNumber('list')->name('lists.comments');
        Route::get('report-reasons', [ReportController::class, 'reasons'])->name('reports.reasons');
    });

    // Catalog browsing, search and playback metadata require an authenticated account.
    // Raw media proxy URLs and static image assets above remain public because players load them without bearer headers.
    // Live TV, provider-neutral: credentials, mirrors and signatures stay server-side.
    Route::prefix('tv')->name('tv.')->middleware(['auth:sanctum', 'throttle:catalog'])->group(function () {
        Route::get('categories', [TvController::class, 'categories'])->name('categories');
        Route::get('channels', [TvController::class, 'channels'])->name('channels');
        // Channel ids are strings on some providers (Vavoo "tf1"), digits on Xtream.
        Route::get('channels/{id}', [TvController::class, 'show'])->name('channels.show');
        Route::get('channels/{id}/epg', [TvController::class, 'epg'])->name('channels.epg');
        Route::get('channels/{id}/stream', [TvController::class, 'stream'])->name('channels.stream');
    });

    // Catalog metadata, title search and playback helpers require an account. The active profile
    // header is validated and used for age filtering.
    Route::prefix('catalog')->name('catalog.')->middleware(['auth:sanctum', 'throttle:catalog', ResolveProfile::class])->group(function () {
        Route::get('home', [CatalogController::class, 'home'])->name('home');
        Route::get('releases', [WeeklyReleasesController::class, 'index'])->name('releases');
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
        Route::get('{type}/{id}/watch-providers', [CatalogTitleController::class, 'watchProviders'])
            ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('watch-providers');
        Route::get('{type}/{id}/recommendations', [CatalogTitleController::class, 'recommendations'])
            ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('recommendations');
        Route::get('{type}/{id}/credits', [CatalogTitleController::class, 'credits'])
            ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('credits');
        // « Affiches, arrière-plans, logos »: what TMDb has, and the active profile's choice.
        Route::get('{type}/{id}/images', [TitleArtworkController::class, 'images'])
            ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('images');

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
            Route::middleware('throttle:social')->group(function () {
                // Comments (docs/social.md).
                Route::post('reviews/{review}/comments', [ReviewSocialController::class, 'comment'])->whereNumber('review')->name('reviews.comment');
                Route::delete('comments/{comment}', [ReviewSocialController::class, 'deleteComment'])->whereNumber('comment')->name('comments.destroy');
                // Blocked everywhere, or hidden from the feed only.
                Route::post('profiles/{username}/block', [SocialProfileController::class, 'block'])->name('profiles.block');
                Route::delete('profiles/{username}/block', [SocialProfileController::class, 'unblock'])->name('profiles.unblock');
                Route::post('profiles/{username}/mute', [SocialProfileController::class, 'mute'])->name('profiles.mute');
                Route::delete('profiles/{username}/mute', [SocialProfileController::class, 'unmute'])->name('profiles.unmute');
                Route::get('hidden', [SocialProfileController::class, 'hidden'])->name('hidden');
                // The feed and the accounts to follow.
                Route::get('feed', [FeedController::class, 'index'])->name('feed');
                Route::get('feed/for-you', [FeedController::class, 'forYou'])->name('feed.for-you');
                Route::get('suggestions', [FeedController::class, 'suggestions'])->name('suggestions');
                // Lists and top 10s of the active profile.
                Route::get('lists', [ListController::class, 'mine'])->name('lists.mine');
                Route::post('lists', [ListController::class, 'store'])->name('lists.store');
                Route::whereNumber('list')->group(function () {
                    Route::put('lists/{list}', [ListController::class, 'update'])->name('lists.update');
                    Route::delete('lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');
                    Route::post('lists/{list}/items', [ListController::class, 'addItem'])->name('lists.items.store');
                    Route::delete('lists/{list}/items/{type}/{id}', [ListController::class, 'removeItem'])
                        ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('lists.items.destroy');
                    Route::put('lists/{list}/like', [ListController::class, 'like'])->name('lists.like');
                    Route::delete('lists/{list}/like', [ListController::class, 'unlike'])->name('lists.unlike');
                    Route::post('lists/{list}/comments', [ListController::class, 'comment'])->name('lists.comment');
                });
            });
            // Reports to the moderation (docs/moderation.md).
            Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
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

        // Push notifications of this app (docs/notifications.md).
        Route::prefix('push/device')->name('push.device.')->middleware(['throttle:push', ResolveProfile::class])->group(function () {
            Route::get('/', [PushDeviceController::class, 'show'])->name('show');
            Route::put('/', [PushDeviceController::class, 'update'])->name('update');
            Route::delete('/', [PushDeviceController::class, 'destroy'])->name('destroy');
            Route::post('test', [PushDeviceController::class, 'test'])->name('test');
        });

        Route::get('avatars', [AvatarController::class, 'index'])->name('avatars');
        Route::get('covers', [CoverController::class, 'index'])->name('covers');
        Route::apiResource('profiles', ProfileController::class)->only(['index', 'store', 'show', 'update']);
        // Its own photo, instead of its avatar (docs/social.md).
        Route::post('profiles/{profile}/photo', [ProfilePhotoController::class, 'store'])
            ->middleware('throttle:profile-photo-upload')
            ->name('profiles.photo.store');
        Route::delete('profiles/{profile}/photo', [ProfilePhotoController::class, 'destroy'])
            ->name('profiles.photo.destroy');
        // Its own cover image, instead of its cover of the catalog (docs/social.md).
        Route::post('profiles/{profile}/cover', [ProfileCoverController::class, 'store'])
            ->middleware('throttle:profile-photo-upload')
            ->name('profiles.cover.store');
        Route::delete('profiles/{profile}/cover', [ProfileCoverController::class, 'destroy'])
            ->name('profiles.cover.destroy');

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

            // Viewings: an episode watched to the end, another viewing (docs/social.md).
            Route::post('diary', [DiaryController::class, 'store'])->name('diary.store');

            // The poster, backdrop and logo the profile chose for a title (docs/images-des-titres.md).
            Route::put('artwork/{type}/{id}', [TitleArtworkController::class, 'update'])
                ->whereIn('type', ['movie', 'tv'])->whereNumber('id')->name('artwork.update');
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
