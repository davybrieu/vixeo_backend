<?php

namespace App\Services\Addons;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbClient;

/**
 * Turns the ids of plug-ins into TMDb titles, and back. Plug-ins name a title `tt0137523` (IMDb,
 * the most common) or `tmdb:550`; Vixeo opens every title by its TMDb id, so a plug-in's row only
 * shows titles TMDb knows (others are left out), and they go through the age filter like the rest.
 */
class TitleIds
{
    public function __construct(private TmdbClient $tmdb) {}

    /**
     * TMDb titles (list-item shape, with `media_type`) for plug-in ids, in one batch. A title TMDb
     * does not know, an adult title or an id Vixeo cannot read maps to null.
     *
     * @param  array<array-key, array{MediaType, string}>  $refs
     * @return array<array-key, array<string, mixed>|null>
     */
    public function items(array $refs): array
    {
        $requests = [];
        foreach ($refs as $key => [$type, $id]) {
            if (preg_match('/^tmdb:(\d+)$/', $id, $m) === 1) {
                $requests["tmdb:{$type->value}:{$m[1]}"] = ["{$type->value}/{$m[1]}", []];
            } elseif (preg_match('/^tt\d{5,10}$/', $id) === 1) {
                $requests["imdb:{$id}"] = ["find/{$id}", ['external_source' => 'imdb_id']];
            }
        }

        // IMDb ids never change TMDb title: cached a week (like age ratings).
        $found = $requests === [] ? [] : $this->tmdb->getEach(
            array_filter($requests, fn (string $name) => str_starts_with($name, 'imdb:'), ARRAY_FILTER_USE_KEY),
            'ratings',
        ) + $this->tmdb->getEach(
            array_filter($requests, fn (string $name) => str_starts_with($name, 'tmdb:'), ARRAY_FILTER_USE_KEY),
        );

        $items = [];
        foreach ($refs as $key => [$type, $id]) {
            $items[$key] = null;
            if (preg_match('/^tmdb:(\d+)$/', $id, $m) === 1) {
                $items[$key] = $this->fromDetails($found["tmdb:{$type->value}:{$m[1]}"] ?? null, $type);
            } elseif (isset($found["imdb:{$id}"])) {
                $results = $found["imdb:{$id}"][$type === MediaType::Movie ? 'movie_results' : 'tv_results'] ?? [];
                $item = is_array($results[0] ?? null) ? $results[0] : null;
                $items[$key] = $item === null || ! empty($item['adult']) ? null : ['media_type' => $type->value] + $item;
            }
        }

        return $items;
    }

    /** The IMDb id of a TMDb title (plug-ins mostly ask for it), cached with the title. */
    public function imdbId(MediaType $type, int $tmdbId): ?string
    {
        $ids = $this->tmdb->getEach(['ids' => ["{$type->value}/{$tmdbId}/external_ids", []]])['ids'] ?? null;
        $imdb = is_array($ids) ? ($ids['imdb_id'] ?? null) : null;

        return is_string($imdb) && preg_match('/^tt\d+$/', $imdb) === 1 ? $imdb : null;
    }

    /**
     * Details of a title, in the shape of a list item.
     *
     * @param  array<string, mixed>|null  $details
     * @return array<string, mixed>|null
     */
    private function fromDetails(?array $details, MediaType $type): ?array
    {
        if ($details === null || ! isset($details['id']) || ! empty($details['adult'])) {
            return null;
        }

        $details['media_type'] = $type->value;
        $details['genre_ids'] = array_values(array_filter(array_map(
            fn ($genre) => is_array($genre) && isset($genre['id']) ? (int) $genre['id'] : null,
            is_array($details['genres'] ?? null) ? $details['genres'] : [],
        )));

        return $details;
    }
}
