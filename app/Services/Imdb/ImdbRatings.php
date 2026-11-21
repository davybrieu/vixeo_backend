<?php

namespace App\Services\Imdb;

use App\Enums\MediaType;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbFormat as F;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * IMDb ratings of the titles and episodes the API gives (docs/notes.md), read from the tables
 * imported by `imdb:import` (ImdbDatasets). The link is the IMDb id: the title's (`imdb_id` of a
 * movie, `external_ids` of a TV show), else TMDb's External IDs. An episode: its number in its
 * show's IMDb episodes, else its own External IDs. No IMDb rating: the apps show TMDb's.
 */
class ImdbRatings
{
    public function __construct(private TmdbClient $tmdb) {}

    /**
     * A movie / TV show of TMDb with `imdb_id` (found or null) and `imdb_rating`
     * (`{id, rating, votes}` or null).
     *
     * @param  array<string, mixed>  $title
     * @return array<string, mixed>
     */
    public function attach(MediaType $type, int $id, array $title): array
    {
        $imdbId = self::imdbIdOf($title);
        // A movie: its External IDs when its details have none (a TV show already has them).
        if ($imdbId === null && ! array_key_exists('external_ids', $title)) {
            $imdbId = $this->externalImdbId("{$type->value}/{$id}/external_ids");
        }
        $title['imdb_id'] = $imdbId;
        $title['imdb_rating'] = $imdbId === null ? null : ($this->many([$imdbId])[$imdbId] ?? null);

        return $title;
    }

    /**
     * Episodes of a season (TMDb) with their `imdb_rating`.
     *
     * @param  list<array<string, mixed>>  $episodes
     * @return list<array<string, mixed>>
     */
    public function attachToEpisodes(int $showId, int $season, array $episodes): array
    {
        if ($episodes === []) {
            return $episodes;
        }
        $showImdbId = $this->externalImdbId("tv/{$showId}/external_ids");
        $byNumber = $showImdbId === null ? [] : $this->seasonRatings($showImdbId, $season);

        // Not found by its number: the episode's own External IDs (aired episodes only).
        $paths = [];
        foreach ($episodes as $i => $episode) {
            $number = (int) ($episode['episode_number'] ?? 0);
            $aired = ($date = F::string($episode, 'air_date')) !== null && $date <= now()->toDateString();
            if (! isset($byNumber[$number]) && $aired) {
                $paths[$i] = "tv/{$showId}/season/{$season}/episode/{$number}/external_ids";
            }
        }
        $found = [];
        if ($paths !== []) {
            try {
                $responses = $this->tmdb->getMany(array_values($paths), 'details');
                foreach (array_keys($paths) as $i) {
                    $imdbId = self::imdbIdOf((array) ($responses[$paths[$i]] ?? []));
                    if ($imdbId !== null) {
                        $found[$i] = $imdbId;
                    }
                }
            } catch (Throwable) {
                // TMDb unavailable: these episodes keep TMDb's rating only.
            }
        }
        $ratings = $this->many(array_values($found));

        foreach ($episodes as $i => &$episode) {
            $number = (int) ($episode['episode_number'] ?? 0);
            $episode['imdb_rating'] = $byNumber[$number] ?? (isset($found[$i]) ? ($ratings[$found[$i]] ?? null) : null);
        }
        unset($episode);

        return $episodes;
    }

    /**
     * Ratings of IMDb ids.
     *
     * @param  list<string>  $imdbIds
     * @return array<string, array{id: string, rating: float, votes: int}>
     */
    public function many(array $imdbIds): array
    {
        $ids = array_values(array_unique(array_filter($imdbIds, ImdbDatasets::isTitleId(...))));
        if ($ids === []) {
            return [];
        }

        return DB::table('imdb_ratings')->whereIn('tconst', $ids)->get()
            ->mapWithKeys(fn ($row) => [$row->tconst => self::format($row)])
            ->all();
    }

    /** @return array<int, array{id: string, rating: float, votes: int}> by episode number */
    private function seasonRatings(string $showImdbId, int $season): array
    {
        return DB::table('imdb_episodes')
            ->join('imdb_ratings', 'imdb_ratings.tconst', '=', 'imdb_episodes.tconst')
            ->where('imdb_episodes.parent_tconst', $showImdbId)
            ->where('imdb_episodes.season', $season)
            ->whereNotNull('imdb_episodes.episode')
            ->get(['imdb_episodes.episode', 'imdb_ratings.tconst', 'imdb_ratings.rating', 'imdb_ratings.votes'])
            ->mapWithKeys(fn ($row) => [(int) $row->episode => self::format($row)])
            ->all();
    }

    /** IMDb id of TMDb's External IDs at [path]; null if none or TMDb is unavailable. */
    private function externalImdbId(string $path): ?string
    {
        try {
            return self::imdbIdOf($this->tmdb->get($path, [], 'details'));
        } catch (Throwable) {
            return null;
        }
    }

    /** @param  array<string, mixed>  $data  TMDb details (`imdb_id`) or External IDs */
    private static function imdbIdOf(array $data): ?string
    {
        foreach ([$data['imdb_id'] ?? null, $data['external_ids']['imdb_id'] ?? null] as $id) {
            if (ImdbDatasets::isTitleId($id)) {
                return $id;
            }
        }

        return null;
    }

    /** @return array{id: string, rating: float, votes: int} */
    private static function format(object $row): array
    {
        return ['id' => (string) $row->tconst, 'rating' => (float) $row->rating, 'votes' => (int) $row->votes];
    }
}
