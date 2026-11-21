<?php

use App\Http\Controllers\SiteController;
use App\Support\Usernames;
use Illuminate\Support\Facades\Route;

/*
| Presentation site (www.vixeo.app): Vue pages rendered by Inertia (SSR). Middleware group
| `site` (bootstrap/app.php): no session, no CSRF token, no cookie.
*/
Route::controller(SiteController::class)->name('site.')->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/apk', 'apk')->name('apk');
    Route::get('/mentions-legales', 'legal')->name('legal');
    Route::get('/confidentialite', 'privacy')->name('privacy');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
    Route::get('/robots.txt', 'robots')->name('robots');
    Route::get('/.well-known/assetlinks.json', 'assetLinks')->name('assetlinks');
    // Old address of a profile's page (www.vixeo.app/@pseudo): now /pseudo.
    Route::permanentRedirect('/@{username}', '/{username}')->where('username', Usernames::PATTERN);
    // Last: public page of a profile, shared from the app (« Partager »): www.vixeo.app/pseudo,
    // like Twitter (developer's request, 21/11/2026). The pages above come first, and no username
    // is one of them (Usernames::RESERVED). On Android, the app opens these links itself (Android
    // App Links, assetlinks.json).
    Route::get('/{username}', 'member')->where('username', Usernames::PATTERN)
        ->middleware('throttle:members')->name('member');
});

// One page: installing and the FAQ are sections of the home page (#installer, #faq). The old
// /installer and /faq pages and the short links /telecharger, /android, /tv are gone: 404
// (developer's request). /apk stays: the address to type on a TV.
