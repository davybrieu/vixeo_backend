<?php

namespace App\Services\Addons;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbClient;

/**
 * The ids plug-ins know a title by. Vixeo opens every title by its TMDb id; plug-ins mostly name
 * it by its IMDb id (`tt0137523`), sometimes `tmdb:550`.
 */
class TitleIds
{
    public function __construct(private TmdbClient $tmdb) {}

    /** The IMDb id of a TMDb title (plug-ins mostly ask for it), cached with the title. */
    public function imdbId(MediaType $type, int $tmdbId): ?string
    {
        $ids = $this->tmdb->getEach(['ids' => ["{$type->value}/{$tmdbId}/external_ids", []]])['ids'] ?? null;
        $imdb = is_array($ids) ? ($ids['imdb_id'] ?? null) : null;

        return is_string($imdb) && preg_match('/^tt\d+$/', $imdb) === 1 ? $imdb : null;
    }
}
