<?php

namespace App\Support;

/**
 * Languages a profile can prefer for audio and subtitles. Codes follow OpenSubtitles (ISO 639-1,
 * with a region where it matters). The app keeps the French names (vixeo_frontend).
 */
final class Languages
{
    /** Preferred audio track (null = original version). */
    public const AUDIO = ['fr', 'en', 'es', 'de', 'it', 'pt', 'nl', 'ar', 'ja', 'ko', 'zh', 'ru', 'pl', 'tr', 'hi'];

    /** Subtitles (null = none). */
    public const SUBTITLES = [
        'fr', 'en', 'es', 'de', 'it', 'pt-PT', 'pt-BR', 'nl', 'ar', 'ja', 'ko', 'zh-CN', 'zh-TW', 'ru', 'pl', 'tr', 'hi',
    ];
}
