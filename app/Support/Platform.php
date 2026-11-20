<?php

namespace App\Support;

/**
 * The visitor's screen, guessed from the User-Agent: the site shows the matching install option
 * first. No iOS app yet, no web app any more: anything that is not Android is offered Windows.
 */
enum Platform: string
{
    case Android = 'android';
    case Tv = 'tv';
    case Windows = 'windows';

    public static function fromUserAgent(?string $userAgent): self
    {
        $ua = (string) $userAgent;

        if (! preg_match('/Android/i', $ua)) {
            return self::Windows;
        }

        // Android TV, Google TV, Chromecast, Fire TV (AFT…), Sony Bravia, Xiaomi, Nvidia Shield…
        $tv = '/Android ?TV|Google ?TV|GoogleTV|Chromecast|\bAFT[A-Z0-9]*\b|BRAVIA|SMART-?TV|MiTV|MIBOX|SHIELD Android TV|\bTV\b/i';

        return preg_match($tv, $ua) ? self::Tv : self::Android;
    }
}
