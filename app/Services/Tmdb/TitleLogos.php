<?php

namespace App\Services\Tmdb;

/**
 * Title logos of list items (home rows), so that the large-screen hero and the phone carousel
 * show the logo at once. Fetched in one batch per list and cached a week, shared by all accounts:
 * only titles new to the home cost a request. Elsewhere (search), the title page gives the logo.
 */
class TitleLogos
{
    public function __construct(private TmdbClient $tmdb) {}

    /**
     * Sets `logo` (see TmdbFormat::logo, null when TMDb has none) on each movie / TV show.
     *
     * @param  array<array-key, list<array<string, mixed>>>  $lists
     * @return array<array-key, list<array<string, mixed>>>
     */
    public function addMany(array $lists): array
    {
        $paths = [];
        foreach ($lists as $items) {
            foreach ($items as $item) {
                if ($path = self::path($item)) {
                    $paths[$path] = true;
                }
            }
        }
        if ($paths === []) {
            return $lists;
        }

        $images = $this->tmdb->getMany(array_keys($paths), 'images', 20, ['include_image_language' => 'fr,en,null']);

        return array_map(fn (array $items) => array_map(function (array $item) use ($images) {
            $path = self::path($item);
            $response = $path === null ? null : ($images[$path] ?? null);
            $item['logo'] = is_array($response) ? TmdbFormat::logo(['images' => $response]) : null;

            return $item;
        }, $items), $lists);
    }

    /** @param  array<string, mixed>  $item */
    private static function path(array $item): ?string
    {
        $type = $item['media_type'] ?? null;

        return in_array($type, ['movie', 'tv'], true) && isset($item['id']) ? $type.'/'.(int) $item['id'].'/images' : null;
    }
}
