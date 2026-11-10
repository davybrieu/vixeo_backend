<?php

use App\Http\Controllers\SiteController;
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
});

// One page: installing and the FAQ are sections of the home page (#installer, #faq). The old
// /installer and /faq pages and the short links /telecharger, /android, /tv are gone: 404
// (developer's request). /apk stays: the address to type on a TV.
