<?php

use Illuminate\Support\Facades\Route;

/*
| Root of the API host (api.vixeo.app), outside /api/v1. Only registered when API_DOMAIN is set:
| without it, the site answers on / (config/vixeo.php).
*/
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));

// The API is not meant for search engines (the site has its own robots.txt).
Route::get('/robots.txt', fn () => response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']));
