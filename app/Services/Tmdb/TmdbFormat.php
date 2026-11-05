<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;

/**
 * Helpers to turn TMDb's loosely typed JSON into clean values.
 * TMDb uses "" for missing strings and dates: they become null here.
 */
final class TmdbFormat
{
    /** Common age ratings mapped to a minimum age (FR first, US as fallback). */
    private const AGE_RATINGS = [
        'U' => 0, 'TP' => 0, 'TOUS PUBLICS' => 0,
        'G' => 0, 'PG' => 10, 'PG-13' => 13, 'R' => 17, 'NC-17' => 18,
        'TV-Y' => 0, 'TV-Y7' => 7, 'TV-G' => 0, 'TV-PG' => 10, 'TV-14' => 14, 'TV-MA' => 17,
    ];

    private const RATING_COUNTRIES = ['FR', 'US'];

    /** Our item type, from the `media_type` TMDb sets on mixed lists (or TmdbCatalog sets). */
    public static function mediaType(mixed $value): MediaType
    {
        return MediaType::tryFrom((string) $value) ?? MediaType::Movie;
    }

    /** @param  array<array-key, mixed>  $data */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param  array<array-key, mixed>  $data */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @param  array<array-key, mixed>  $data */
    public static function float(array $data, string $key): float
    {
        return round((float) ($data[$key] ?? 0), 1);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<int>
     */
    public static function ints(array $data, string $key): array
    {
        return array_values(array_map(intval(...), array_filter(self::array($data, $key), is_numeric(...))));
    }

    public static function year(?string $date): ?int
    {
        return $date !== null && preg_match('/^\d{4}/', $date) ? (int) substr($date, 0, 4) : null;
    }

    /** Logo languages, in order of preference (null = no language). */
    private const LOGO_LANGUAGES = ['fr', 'en', null];

    /**
     * Title logo of a movie / TV show page (`images` appended): French first, then English, then
     * without language; the best rated of each. SVG files are left out (not every client draws
     * them). Null when TMDb has none: the client writes the title.
     *
     * @param  array<string, mixed>  $title
     * @return array{path: string, aspect_ratio: float}|null
     */
    public static function logo(array $title): ?array
    {
        $logos = array_filter(
            self::array(self::array($title, 'images'), 'logos'),
            fn ($logo) => is_array($logo)
                && is_string($logo['file_path'] ?? null)
                && ! str_ends_with(strtolower($logo['file_path']), '.svg')
                && in_array($logo['iso_639_1'] ?? null, self::LOGO_LANGUAGES, true),
        );
        if ($logos === []) {
            return null;
        }

        usort($logos, fn (array $a, array $b) => [
            array_search($a['iso_639_1'] ?? null, self::LOGO_LANGUAGES, true),
            -(float) ($a['vote_average'] ?? 0),
        ] <=> [
            array_search($b['iso_639_1'] ?? null, self::LOGO_LANGUAGES, true),
            -(float) ($b['vote_average'] ?? 0),
        ]);
        $logo = $logos[0];
        $ratio = (float) ($logo['aspect_ratio'] ?? 0);

        return ['path' => (string) $logo['file_path'], 'aspect_ratio' => round($ratio > 0 ? $ratio : 3.0, 3)];
    }

    /**
     * Best rated backdrop without text (`images` appended), to show under the logo. Null when TMDb
     * has none: the main backdrop is used.
     *
     * @param  array<string, mixed>  $title
     */
    public static function textlessBackdrop(array $title): ?string
    {
        $best = null;
        foreach (self::array(self::array($title, 'images'), 'backdrops') as $backdrop) {
            if (! is_array($backdrop) || ($backdrop['iso_639_1'] ?? null) !== null || ! is_string($backdrop['file_path'] ?? null)) {
                continue;
            }
            if ($best === null || (float) ($backdrop['vote_average'] ?? 0) > (float) ($best['vote_average'] ?? 0)) {
                $best = $backdrop;
            }
        }

        return $best === null ? null : (string) $best['file_path'];
    }

    /** Full image URL, e.g. size "w500" or "original". */
    public static function image(?string $path, string $size): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return rtrim(config('services.tmdb.image_base_url'), '/').'/'.$size.$path;
    }

    /**
     * Raw certification ("12", "PG-13"…): French one, else American.
     *
     * @param  array<string, mixed>  $title  movie with release_dates, or show with content_ratings
     */
    public static function certification(array $title): ?string
    {
        foreach (self::RATING_COUNTRIES as $country) {
            foreach (self::array(self::array($title, 'release_dates'), 'results') as $entry) {
                if (($entry['iso_3166_1'] ?? null) === $country) {
                    foreach ($entry['release_dates'] ?? [] as $release) {
                        if (! empty($release['certification'])) {
                            return (string) $release['certification'];
                        }
                    }
                }
            }

            foreach (self::array(self::array($title, 'content_ratings'), 'results') as $entry) {
                if (($entry['iso_3166_1'] ?? null) === $country && ! empty($entry['rating']) && $entry['rating'] !== 'NR') {
                    return (string) $entry['rating'];
                }
            }
        }

        return null;
    }

    /** Minimum age for a certification ("12" → 12, "PG-13" → 13, "U" → 0). */
    public static function ageRating(?string $certification): ?int
    {
        if ($certification === null) {
            return null;
        }

        $normalized = strtoupper(trim($certification));

        if (array_key_exists($normalized, self::AGE_RATINGS)) {
            return self::AGE_RATINGS[$normalized];
        }

        return preg_match('/\d+/', $normalized, $m) ? (int) $m[0] : null;
    }

    /**
     * Best trailer: YouTube, French first, then English; official trailers before teasers.
     *
     * @param  array<string, mixed>  $title
     * @return array<string, mixed>|null
     */
    public static function trailer(array $title): ?array
    {
        $videos = array_filter(
            self::array(self::array($title, 'videos'), 'results'),
            fn ($video) => is_array($video) && ($video['site'] ?? null) === 'YouTube'
                && in_array($video['type'] ?? null, ['Trailer', 'Teaser'], true) && ! empty($video['key']),
        );

        $score = fn (array $video) => [
            match ($video['iso_639_1'] ?? null) {
                'fr' => 0, 'en' => 1, default => 2
            },
            ($video['type'] ?? null) === 'Trailer' ? 0 : 1,
            ! empty($video['official']) ? 0 : 1,
        ];

        usort($videos, fn (array $a, array $b) => $score($a) <=> $score($b));

        return $videos[0] ?? null;
    }
}
