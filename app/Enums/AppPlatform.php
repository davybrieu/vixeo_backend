<?php

namespace App\Enums;

/** Platforms whose installed apps check for updates (Android covers phones and Android TV). */
enum AppPlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}
