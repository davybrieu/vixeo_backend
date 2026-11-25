<?php

namespace App\Services\Tmdb;

use App\Models\ProfileItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Calendar of worldwide releases. TMDb dates are calendar dates, not server-local timestamps.
 *
 * Movies and first-air TV shows come from Discover. Episode discovery is progressive: series
 * already present in Vixeo profiles plus the TV shows returned by Discover's air-date window.
 * Upcoming weeks reuse UpcomingEpisodes; previous weeks fetch only season windows intersecting the requested dates.
 */
class WeeklyReleases
{
    private const DISCOVER_PAGES = 5;

    public function __construct(
        private TmdbClient $tmdb,
        private UpcomingEpisodes $episodes,
        private TitleSummaries $summaries,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function between(string $from, string $to, string $type = 'all'): array
    {
        $key = "vixeo:weekly-releases:{$from}:{$to}";

        $staleKey = "{$key}:stale";

        try {
            $items = Cache::remember($key, now()->addHours(12), fn () => $this->aggregate($from, $to));
            // Keep a longer-lived copy so a temporary TMDb outage does not blank the calendar.
            Cache::put($staleKey, $items, now()->addDays(7));
        } catch (TmdbUnavailableException $exception) {
            $items = Cache::get($staleKey);
            if (! is_array($items)) {
                throw $exception;
            }
        }

        return array_values(array_filter(
            $items,
            fn (array $item) => $type === 'all' || $item['type'] === $type,
        ));
    }

    /** Refresh the current and following calendar weeks outside user requests. */
    public function refreshUpcoming(): array
    {
        $monday = CarbonImmutable::now('UTC')->startOfWeek()->startOfDay();
        $refreshed = [];

        foreach ([$monday, $monday->addWeek()] as $start) {
            $from = $start->toDateString();
            $to = $start->addDays(6)->toDateString();
            Cache::forget("vixeo:weekly-releases:{$from}:{$to}");
            $items = $this->aggregate($from, $to);
            Cache::put("vixeo:weekly-releases:{$from}:{$to}", $items, now()->addHours(24));
            Cache::put("vixeo:weekly-releases:{$from}:{$to}:stale", $items, now()->addDays(7));
            $refreshed[] = ['from' => $from, 'to' => $to, 'count' => count($items)];
        }

        return $refreshed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aggregate(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from, 'UTC')->startOfDay();
        $end = CarbonImmutable::parse($to, 'UTC')->endOfDay();

        $items = [
            ...$this->movies($from, $to),
            ...$this->newShows($from, $to),
            ...$this->episodes($start, $end),
        ];

        // A stable identity per TMDb movie / show / season / episode removes duplicate discoveries.
        $unique = [];
        foreach ($items as $item) {
            $key = match ($item['type']) {
                'episode' => "episode:{$item['tmdb_id']}:{$item['season_number']}:{$item['episode_number']}",
                default => "{$item['type']}:{$item['tmdb_id']}",
            };
            $unique[$key] ??= $item;
        }

        $items = array_values($unique);
        usort($items, fn (array $a, array $b) => [$a['release_date'], $a['title'], $a['tmdb_id']] <=> [$b['release_date'], $b['title'], $b['tmdb_id']]);

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movies(string $from, string $to): array
    {
        $candidates = $this->discover('movie', [
            'release_date.gte' => $from,
            'release_date.lte' => $to,
            'sort_by' => 'release_date.asc',
            'with_release_type' => '2|3|4',
            'include_adult' => false,
        ]);

        $paths = [];
        foreach ($candidates as $movie) {
            if (isset($movie['id'])) {
                $paths[] = 'movie/'.(int) $movie['id'];
            }
        }

        $details = $paths === [] ? [] : $this->tmdb->getMany(
            $paths,
            'details',
            8,
            ['append_to_response' => 'release_dates'],
        );

        $items = [];
        foreach ($candidates as $movie) {
            $id = (int) ($movie['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $detail = $details["movie/{$id}"] ?? [];
            $release = $this->worldwideRelease($detail, $from, $to);
            if ($release === null) {
                $date = $movie['release_date'] ?? null;
                if (! is_string($date) || $date < $from || $date > $to) {
                    continue;
                }
                $release = ['date' => $date, 'release_type' => null, 'country' => null];
            }

            $items[] = [
                'type' => 'movie',
                'media_type' => 'movie',
                'tmdb_id' => $id,
                'id' => $id,
                'title' => (string) ($movie['title'] ?? $movie['original_title'] ?? 'Titre inconnu'),
                'poster_url' => TmdbFormat::image(TmdbFormat::string($movie, 'poster_path'), 'w342'),
                'release_date' => $release['date'],
                'release_type' => $release['release_type'],
                'country' => $release['country'],
                'country_code' => $release['country'],
                'overview' => TmdbFormat::string($movie, 'overview'),
                'navigation' => ['type' => 'movie', 'id' => $id],
            ];
        }

        return $items;
    }

    /**
     * Choose the earliest known country release inside the requested week. Discover's global
     * release_date remains the fallback when country-level dates are absent.
     *
     * @param  array<string, mixed>  $movie
     * @return array{date: string, release_type: ?string, country: ?string}|null
     */
    private function worldwideRelease(array $movie, string $from, string $to): ?array
    {
        $candidates = [];
        foreach (TmdbFormat::array(TmdbFormat::array($movie, 'release_dates'), 'results') as $country) {
            if (! is_array($country)) {
                continue;
            }
            $code = TmdbFormat::string($country, 'iso_3166_1');
            foreach (TmdbFormat::array($country, 'release_dates') as $release) {
                if (! is_array($release)) {
                    continue;
                }
                $date = TmdbFormat::string($release, 'release_date');
                $kind = TmdbFormat::int($release, 'type');
                if ($date === null || $kind === null || ! in_array($kind, [2, 3, 4], true)) {
                    continue;
                }
                $date = substr($date, 0, 10);
                if ($date >= $from && $date <= $to) {
                    $candidates[] = [
                        'date' => $date,
                        'release_type' => match ($kind) {
                            2, 3 => 'Cinéma',
                            4 => 'Numérique',
                        },
                        'country' => $code,
                    ];
                }
            }
        }

        usort($candidates, fn (array $a, array $b) => [$a['date'], $a['country']] <=> [$b['date'], $b['country']]);

        return $candidates[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function newShows(string $from, string $to): array
    {
        $shows = $this->discover('tv', [
            'first_air_date.gte' => $from,
            'first_air_date.lte' => $to,
            'sort_by' => 'first_air_date.asc',
        ]);

        $items = [];
        foreach ($shows as $show) {
            $id = (int) ($show['id'] ?? 0);
            $date = $show['first_air_date'] ?? null;
            if ($id <= 0 || ! is_string($date) || $date < $from || $date > $to) {
                continue;
            }
            $items[] = [
                'type' => 'tv',
                'media_type' => 'tv',
                'tmdb_id' => $id,
                'id' => $id,
                'title' => (string) ($show['name'] ?? $show['original_name'] ?? 'Série inconnue'),
                'poster_url' => TmdbFormat::image(TmdbFormat::string($show, 'poster_path'), 'w342'),
                'release_date' => $date,
                'release_type' => 'Première diffusion',
                'country' => TmdbFormat::array($show, 'origin_country')[0] ?? null,
                'country_code' => TmdbFormat::array($show, 'origin_country')[0] ?? null,
                'overview' => TmdbFormat::string($show, 'overview'),
                'navigation' => ['type' => 'tv', 'id' => $id],
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function episodes(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // Vixeo-known series across all profiles, plus series discovered through the TMDb air-date
        // window. No full-catalogue scan and no request per series: UpcomingEpisodes batches details
        // and season requests and reads only seasons with a known next/last episode.
        $known = ProfileItem::query()
            ->where('source', 'tmdb')
            ->where('type', 'tv')
            ->where(fn ($query) => $query->where('is_favorite', true)
                ->orWhere('is_watched', true)
                ->orWhereNotNull('last_engaged_at'))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->pluck('item_id')
            ->map(fn (string $value) => (int) substr($value, strrpos($value, ':') !== false ? strrpos($value, ':') + 1 : 0))
            ->filter(fn (int $id) => $id > 0)
            ->all();

        $discovered = $this->discover('tv', [
            'air_date.gte' => $from->toDateString(),
            'air_date.lte' => $to->toDateString(),
            'sort_by' => 'first_air_date.asc',
        ]);

        foreach ($discovered as $show) {
            if (isset($show['id'])) {
                $known[] = (int) $show['id'];
            }
        }

        $ids = array_slice(array_values(array_unique(array_filter($known))), 0, 250);
        if ($ids === []) {
            return [];
        }

        // Past weeks need season lookup based on TMDb season air dates: last_episode_to_air now
        // points beyond the selected week. For current/future weeks, reuse the existing optimized
        // next/last-episode helper.
        if ($to->lt(CarbonImmutable::now('UTC')->startOfDay())) {
            return $this->pastEpisodes($ids, $from, $to);
        }

        // The existing helper estimates release instants by network timezone. Widen the instant
        // window, then select by TMDb's literal air_date so a UTC conversion never changes a day.
        $episodes = $this->episodes->availableBetween(
            $ids,
            $from->subDay(),
            $to->addDay(),
        );

        $items = [];
        foreach ($episodes as $showId => $result) {
            $show = $result['show'];
            foreach ($result['episodes'] as $entry) {
                $episode = $entry['episode'];
                $date = TmdbFormat::string($episode, 'air_date');
                if ($date === null || $date < $from->toDateString() || $date > $to->toDateString()) {
                    continue;
                }
                $season = (int) ($episode['season_number'] ?? 0);
                $number = (int) ($episode['episode_number'] ?? 0);
                $items[] = [
                    'type' => 'episode',
                    'media_type' => 'tv',
                    'tmdb_id' => (int) $showId,
                    'id' => (int) $showId,
                    'episode_id' => (int) ($episode['id'] ?? 0),
                    'title' => (string) ($show['name'] ?? 'Série inconnue'),
                    'episode_name' => TmdbFormat::string($episode, 'name'),
                    'poster_url' => TmdbFormat::image(TmdbFormat::string($show, 'poster_path'), 'w342'),
                    'release_date' => $date,
                    'release_type' => 'Épisode',
                    'country' => TmdbFormat::array($show, 'origin_country')[0] ?? null,
                    'country_code' => TmdbFormat::array($show, 'origin_country')[0] ?? null,
                    'overview' => TmdbFormat::string($episode, 'overview'),
                    'season_number' => $season,
                    'episode_number' => $number,
                    'navigation' => ['type' => 'tv', 'id' => (int) $showId],
                ];
            }
        }

        return $items;
    }

    /**
     * Episodes from previous weeks. Discover's air-date query has already narrowed the candidate
     * shows; inspect only the season summaries of those shows and fetch seasons whose date window
     * intersects the requested period (normally one or two seasons per show).
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function pastEpisodes(array $ids, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $seasons = [];
        foreach ($this->summaries->shows($ids) as $showId => $show) {
            if ($show === null) {
                continue;
            }
            $list = array_values(array_filter(TmdbFormat::array($show, 'seasons'), 'is_array'));
            usort($list, fn (array $a, array $b) => (TmdbFormat::string($a, 'air_date') ?? '9999-99-99')
                <=> (TmdbFormat::string($b, 'air_date') ?? '9999-99-99'));

            foreach ($list as $index => $season) {
                $date = TmdbFormat::string($season, 'air_date');
                $nextDate = isset($list[$index + 1]) ? TmdbFormat::string($list[$index + 1], 'air_date') : null;
                if ($date === null || $date > $to->toDateString() || ($nextDate !== null && $nextDate <= $from->toDateString())) {
                    continue;
                }
                $number = (int) ($season['season_number'] ?? 0);
                $path = "tv/{$showId}/season/{$number}";
                $seasons[$path] = ['show' => $show, 'show_id' => (int) $showId];
            }
        }

        $responses = $seasons === [] ? [] : $this->tmdb->getMany(array_keys($seasons), 'details', 8);
        $items = [];
        foreach ($seasons as $path => $data) {
            $show = $data['show'];
            foreach (TmdbFormat::array($responses[$path] ?? [], 'episodes') as $episode) {
                if (! is_array($episode)) {
                    continue;
                }
                $date = TmdbFormat::string($episode, 'air_date');
                if ($date === null || $date < $from->toDateString() || $date > $to->toDateString()) {
                    continue;
                }
                $showId = $data['show_id'];
                $items[] = [
                    'type' => 'episode',
                    'media_type' => 'tv',
                    'tmdb_id' => $showId,
                    'id' => $showId,
                    'episode_id' => (int) ($episode['id'] ?? 0),
                    'title' => (string) ($show['name'] ?? 'Série inconnue'),
                    'episode_name' => TmdbFormat::string($episode, 'name'),
                    'poster_url' => TmdbFormat::image(TmdbFormat::string($show, 'poster_path'), 'w342'),
                    'release_date' => $date,
                    'release_type' => 'Épisode',
                    'country' => TmdbFormat::array($show, 'origin_country')[0] ?? null,
                    'country_code' => TmdbFormat::array($show, 'origin_country')[0] ?? null,
                    'overview' => TmdbFormat::string($episode, 'overview'),
                    'season_number' => (int) ($episode['season_number'] ?? 0),
                    'episode_number' => (int) ($episode['episode_number'] ?? 0),
                    'navigation' => ['type' => 'tv', 'id' => $showId],
                ];
            }
        }

        return $items;
    }

    /**
     * Discover results progressively; the shared TMDb client caches each page.
     *
     * @param  array<string, scalar>  $query
     * @return list<array<string, mixed>>
     */
    private function discover(string $type, array $query): array
    {
        $items = [];
        for ($page = 1; $page <= self::DISCOVER_PAGES; $page++) {
            $response = $this->tmdb->get("discover/{$type}", [...$query, 'page' => $page], 'lists');
            $results = TmdbFormat::array($response, 'results');
            foreach ($results as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $items[(int) $item['id']] = $item;
                }
            }
            if ($page >= (int) ($response['total_pages'] ?? 1)) {
                break;
            }
        }

        return array_values($items);
    }
}
