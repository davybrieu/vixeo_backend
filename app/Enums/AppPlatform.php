<?php

namespace App\Enums;

/** Platforms whose installed apps check for updates (Android covers phones and Android TV). */
enum AppPlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
    case Windows = 'windows';

    /** Extension of the file a release offers ("apk", "exe"), null when there is none (iOS). */
    public function fileExtension(): ?string
    {
        return match ($this) {
            self::Android => 'apk',
            self::Windows => 'exe',
            self::Ios => null,
        };
    }
}
