<?php

use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

/*
| Presentation site (www.vixeo.app): Vue pages rendered by Inertia (SSR). Middleware group
| `site` (bootstrap/app.php): no session, no CSRF token, no cookie.
*/
Route::controller(SiteController::class)->name('site.')->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/installer', 'install')->name('install');
    Route::get('/apk', 'apk')->name('apk');
    Route::get('/faq', 'faq')->name('faq');
    Route::get('/mentions-legales', 'legal')->name('legal');
    Route::get('/confidentialite', 'privacy')->name('privacy');
    Route::get('/sitemap.xml', 'sitemap')->name('sitemap');
    Route::get('/robots.txt', 'robots')->name('robots');
});

// Short links: the API sends apps to /installer#android and /telecharger (docs/mise-a-jour.md).
Route::permanentRedirect('/telecharger', '/installer');
Route::permanentRedirect('/android', '/installer#android');
Route::permanentRedirect('/tv', '/installer#tv');
