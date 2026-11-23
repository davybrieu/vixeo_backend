<?php

namespace App\Services\Social;

use App\Enums\MediaType;
use App\Services\Tmdb\TitleSummaries;
use App\Services\Tmdb\TmdbFormat as F;
use Throwable;

/**
 * What the social features keep of a title (a list, the 4 favorites, the diary, a message): its
 * name, year, images, genres and length, from TMDb (the cache of App\Services\Tmdb\TitleSummaries,
 * one request for many titles).
 */
class TitleSnapshots
{
    public function __construct(private TitleSummaries $summaries) {}

    /** `movie:550` → [movie, 550]; null for anything else. @return array{MediaType, int}|null */
    public static function parse(string $itemId): ?array
    {
        if (preg_match('/^(movie|tv):(\d{1,9})$/', $itemId, $m) !== 1 || (int) $m[2] === 0) {
            return null;
        }

        return [MediaType::from($m[1]), (int) $m[2]];
    }

    /**
     * The titles TMDb knows among [$itemIds] (`movie:550`), by item id. Empty when TMDb is down.
     *
     * @param  list<string>  $itemIds
     * @return array<string, array{type: string, id: int, title: string, year: int|null, poster_path: string|null, backdrop_path: string|null, genres: list<string>, runtime: int|null}>
     */
    public function of(array $itemIds): array
    {
        $titles = array_values(array_filter(array_map(self::parse(...), array_values(array_unique($itemIds)))));
        if ($titles === []) {
            return [];
        }
        try {
            $details = $this->summaries->titles($titles);
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $snapshots = [];
        foreach ($details as $title) {
            $type = (string) $title['media_type'];
            $movie = $type === 'movie';
            $runtime = $movie ? F::int($title, 'runtime') : (F::ints($title, 'episode_run_time')[0] ?? null);
            if ($runtime === null && ! $movie && is_array($title['last_episode_to_air'] ?? null)) {
                $runtime = F::int($title['last_episode_to_air'], 'runtime');
            }
            $snapshots["{$type}:".(int) $title['id']] = [
                'type' => $type,
                'id' => (int) $title['id'],
                'title' => (string) (F::string($title, $movie ? 'title' : 'name') ?? ''),
                'year' => F::year(F::string($title, $movie ? 'release_date' : 'first_air_date')),
                'poster_path' => F::string($title, 'poster_path'),
                'backdrop_path' => F::string($title, 'backdrop_path'),
                'genres' => array_values(array_map('strval', $title['summary']['genres'] ?? [])),
                'runtime' => $runtime !== null && $runtime > 0 ? $runtime : null,
            ];
        }

        return $snapshots;
    }

    /**
     * A title as the app shows it in a list or a card.
     *
     * @param  array{type: string, id: int, title: string, year?: int|null, poster_path?: string|null, backdrop_path?: string|null}  $snapshot
     * @return array{type: string, id: int, item_id: string, title: string, year: int|null, poster_url: string|null, backdrop_url: string|null}
     */
    public static function card(array $snapshot): array
    {
        return [
            'type' => (string) $snapshot['type'],
            'id' => (int) $snapshot['id'],
            'item_id' => $snapshot['type'].':'.$snapshot['id'],
            'title' => (string) $snapshot['title'],
            'year' => isset($snapshot['year']) ? (int) $snapshot['year'] : null,
            'poster_url' => F::image($snapshot['poster_path'] ?? null, 'w342'),
            'backdrop_url' => F::image($snapshot['backdrop_path'] ?? null, 'w780'),
        ];
    }
}
