<?php

use App\Support\Platform;

it('recognises the visitor device', function (?string $userAgent, Platform $expected) {
    expect(Platform::fromUserAgent($userAgent))->toBe($expected);
})->with([
    'Android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36', Platform::Android],
    'Android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 Chrome/130.0 Safari/537.36', Platform::Android],
    'Google TV' => ['Mozilla/5.0 (Linux; Android 12; Chromecast) AppleWebKit/537.36 Chrome/120.0 Safari/537.36', Platform::Tv],
    'Android TV' => ['Mozilla/5.0 (Linux; Android 11; BRAVIA 4K VH2 Build/RTT1.220304.001) AppleWebKit/537.36', Platform::Tv],
    'Fire TV' => ['Mozilla/5.0 (Linux; Android 9; AFTMM Build/PS7633) AppleWebKit/537.36 Silk/120.0', Platform::Tv],
    'Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36', Platform::Windows],
    // No iOS app yet, no web app any more: the Windows version is shown first.
    'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148', Platform::Windows],
    'none' => [null, Platform::Windows],
]);
