<?php

namespace App\Support;

/**
 * The visitor's screen, guessed from the User-Agent: the site selects the matching install option
 * first. macOS, iPhone and iPad are shown as « Bientôt disponible » (developer's request,
 * 21/11/2026); anything else is offered Windows. An iPad in desktop mode says « Macintosh »: it
 * gets macOS.
 */
enum Platform: string
{
    case Android = 'android';
    case Tv = 'tv';
    case Windows = 'windows';
    case Mac = 'macos';
    case Iphone = 'ios';
    case Ipad = 'ipados';

    public static function fromUserAgent(?string $userAgent): self
    {
        $ua = (string) $userAgent;

        if (! preg_match('/Android/i', $ua)) {
            // iOS says « like Mac OS X »: read before macOS.
            return match (true) {
                (bool) preg_match('/iPad/i', $ua) => self::Ipad,
                (bool) preg_match('/iPhone|iPod/i', $ua) => self::Iphone,
                (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => self::Mac,
                default => self::Windows,
            };
        }

        // Android TV, Google TV, Chromecast, Fire TV (AFT…), Sony Bravia, Xiaomi, Nvidia Shield…
        $tv = '/Android ?TV|Google ?TV|GoogleTV|Chromecast|\bAFT[A-Z0-9]*\b|BRAVIA|SMART-?TV|MiTV|MIBOX|SHIELD Android TV|\bTV\b/i';

        return preg_match($tv, $ua) ? self::Tv : self::Android;
    }
}
