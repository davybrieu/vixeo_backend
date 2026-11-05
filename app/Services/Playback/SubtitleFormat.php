<?php

namespace App\Services\Playback;

/**
 * Subtitle files as WebVTT, the format every player reads (web, Android, iOS).
 */
final class SubtitleFormat
{
    /** SRT (or WebVTT) to WebVTT, in UTF-8 with "\n" line ends. */
    public static function toWebVtt(string $raw): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (! mb_check_encoding($text, 'UTF-8')) {
            // Old SRT files are often Windows-1252 (Latin-1 with curly quotes).
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (str_starts_with(ltrim($text), 'WEBVTT')) {
            return ltrim($text);
        }

        // "00:01:02,345" (SRT) → "00:01:02.345" (WebVTT), in timing lines only.
        $text = preg_replace('/^(\d{1,2}:\d{2}:\d{2}),(\d{3}) --> (\d{1,2}:\d{2}:\d{2}),(\d{3})/m', '$1.$2 --> $3.$4', $text) ?? $text;

        return "WEBVTT\n\n".trim($text)."\n";
    }
}
