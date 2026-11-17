<?php

namespace App\Services\Addons;

use App\Enums\CatalogList;
use App\Enums\MediaType;
use App\Enums\SearchType;
use App\Services\Tmdb\TmdbCatalog;
use App\Services\Tmdb\TmdbClient;
use App\Services\Tmdb\TmdbFormat as F;

/**
 * The Vixeo catalog as a plug-in in the Stremio add-on format (docs/plugins.md): the default
 * plug-in of every account, built in (it cannot be removed). Its manifest, catalogs and metadata
 * are served by the API (`/api/v1/addons/vixeo/manifest.json`), so any client that reads the
 * Stremio format (Stremio, Nuvio…) can install it too.
 *
 * Ids are `tmdb:{id}` (movies, series) and `tmdb:{id}:{season}:{episode}` (episodes). The apps
 * themselves keep reading the richer `/catalog/*` routes (logos, summaries, age filter): the same
 * catalog, the same TMDb data.
 */
class VixeoCatalogAddon
{
    /** `id` of the manifest. */
    public const ID = 'app.vixeo.catalog';

    /** Its id in the list of an account's plug-ins (`GET /addons`). */
    public const KEY = 'vixeo';

    public const VERSION = '1.0.0';

    /** Prefix of the catalog ids ("vixeo.trending"…). */
    private const CATALOG_PREFIX = 'vixeo.';

    /** TMDb gives 20 results a page: `skip` 40 = page 3. */
    private const PAGE_SIZE = 20;

    public function __construct(private TmdbCatalog $catalog, private TmdbClient $tmdb) {}

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        $catalogs = [];
        foreach ([MediaType::Movie, MediaType::Tv] as $type) {
            $stremio = AddonManifest::stremioType($type);
            foreach (CatalogList::for($type) as $list) {
                $catalogs[] = [
                    'type' => $stremio,
                    'id' => self::CATALOG_PREFIX.$list->value,
                    'name' => $list->title($type),
                    'extra' => [['name' => 'skip']],
                ];
            }
            $catalogs[] = [
                'type' => $stremio,
                'id' => self::CATALOG_PREFIX.'search',
                'name' => __('Search'),
                'extra' => [['name' => 'search', 'isRequired' => true], ['name' => 'skip']],
            ];
        }

        $site = rtrim((string) config('app.url'), '/');

        return [
            'id' => self::ID,
            'version' => self::VERSION,
            'name' => 'Vixeo',
            'description' => __('The Vixeo catalog: movies and TV shows, with their details, from TMDb.'),
            'logo' => $site.'/apple-touch-icon.png',
            'resources' => ['catalog', 'meta'],
            'types' => ['movie', 'series'],
            'idPrefixes' => ['tmdb:'],
            'catalogs' => $catalogs,
            'behaviorHints' => ['adult' => false, 'p2p' => false, 'configurable' => false, 'configurationRequired' => false],
        ];
    }

    public function parsedManifest(): AddonManifest
    {
        return AddonManifest::fromArray($this->manifest());
    }

    /**
     * A catalog page, `{metas: [...]}`. Unknown catalog: no metas (the Stremio way).
     *
     * @param  array<string, string>  $extra  `skip`, `search`
     * @return array{metas: list<array<string, mixed>>}
     */
    public function catalog(string $stremioType, string $catalogId, array $extra = []): array
    {
        $type = AddonManifest::mediaType($stremioType);
        if ($type === null || ! str_starts_with($catalogId, self::CATALOG_PREFIX)) {
            return ['metas' => []];
        }

        $page = min(TmdbCatalog::MAX_PAGE, intdiv(max(0, (int) ($extra['skip'] ?? 0)), self::PAGE_SIZE) + 1);
        $name = substr($catalogId, strlen(self::CATALOG_PREFIX));

        if ($name === 'search') {
            $query = trim((string) ($extra['search'] ?? ''));
            if ($query === '') {
                return ['metas' => []];
            }
            $results = $this->catalog->searchType(SearchType::from($type->value), $query, $page)['results'];
        } else {
            $list = CatalogList::tryFrom($name);
            if ($list === null || ! $list->supports($type)) {
                return ['metas' => []];
            }
            $results = $this->catalog->list($type, $list, $page)['results'];
        }

        return ['metas' => array_values(array_map(fn (array $item) => $this->preview($item, $type), $results))];
    }

    /**
     * The details of a movie or a series (with its episodes, `videos`), `{meta: {...}}`; null when
     * the id is not one of ours.
     *
     * @return array{meta: array<string, mixed>}|null
     */
    public function meta(string $stremioType, string $id): ?array
    {
        $type = AddonManifest::mediaType($stremioType);
        if ($type === null || preg_match('/^tmdb:(\d+)$/', $id, $match) !== 1) {
            return null;
        }
        $tmdbId = (int) $match[1];

        $title = $type === MediaType::Movie ? $this->catalog->movie($tmdbId) : $this->catalog->show($tmdbId);
        $meta = $this->preview($title, $type);
        $movie = $type === MediaType::Movie;

        $logo = F::logo($title);
        $trailer = F::trailer($title);
        $cast = array_slice(F::array(F::array($title, $movie ? 'credits' : 'aggregate_credits'), 'cast'), 0, 10);
        $crew = F::array(F::array($title, 'credits'), 'crew');
        $imdb = $movie ? F::string($title, 'imdb_id') : F::string(F::array($title, 'external_ids'), 'imdb_id');
        $runtime = $movie ? F::int($title, 'runtime') : (F::ints($title, 'episode_run_time')[0] ?? null);
        $date = F::string($title, $movie ? 'release_date' : 'first_air_date');

        $meta += [
            'poster' => F::image(F::string($title, 'poster_path'), 'w500'),
            'background' => F::image(F::textlessBackdrop($title) ?? F::string($title, 'backdrop_path'), 'original'),
            'logo' => $logo === null ? null : F::image($logo['path'], 'w500'),
            'released' => $date === null ? null : $date.'T00:00:00.000Z',
            'runtime' => $runtime ? "{$runtime} min" : null,
            'genres' => array_values(array_filter(array_map(
                fn ($genre) => is_array($genre) ? ($genre['name'] ?? null) : null,
                F::array($title, 'genres'),
            ))),
            'cast' => array_values(array_filter(array_map(fn ($p) => is_array($p) ? ($p['name'] ?? null) : null, $cast))),
            'director' => $movie ? array_values(array_filter(array_map(
                fn ($p) => is_array($p) && ($p['job'] ?? null) === 'Director' ? ($p['name'] ?? null) : null,
                $crew,
            ))) : null,
            'imdb_id' => $imdb,
            'trailers' => $trailer === null ? [] : [['source' => (string) $trailer['key'], 'type' => 'Trailer']],
        ];

        if (! $movie) {
            $meta['releaseInfo'] = $this->seriesYears($title);
            $meta['videos'] = $this->episodes($tmdbId, $title);
        }

        return ['meta' => self::withoutNulls($meta)];
    }

    /**
     * What a catalog shows of a title.
     *
     * @param  array<string, mixed>  $item  TMDb title (list item or details)
     * @return array<string, mixed>
     */
    private function preview(array $item, MediaType $type): array
    {
        $movie = $type === MediaType::Movie;
        $vote = F::float($item, 'vote_average');

        return self::withoutNulls([
            'id' => 'tmdb:'.(int) ($item['id'] ?? 0),
            'type' => AddonManifest::stremioType($type),
            'name' => (string) F::string($item, $movie ? 'title' : 'name'),
            'poster' => F::image(F::string($item, 'poster_path'), 'w342'),
            'posterShape' => 'poster',
            'background' => F::image(F::string($item, 'backdrop_path'), 'w1280'),
            'description' => F::string($item, 'overview'),
            'releaseInfo' => ($year = F::year(F::string($item, $movie ? 'release_date' : 'first_air_date'))) === null ? null : (string) $year,
            'imdbRating' => $vote > 0 ? number_format($vote, 1, '.', '') : null,
        ]);
    }

    /** "2011-2019", or "2011-" for a series still running. */
    private function seriesYears(array $show): ?string
    {
        $first = F::year(F::string($show, 'first_air_date'));
        if ($first === null) {
            return null;
        }
        $last = F::year(F::string($show, 'last_air_date'));
        $ended = in_array(F::string($show, 'status'), ['Ended', 'Canceled'], true);

        return $ended && $last !== null ? ($last === $first ? (string) $first : "{$first}-{$last}") : "{$first}-";
    }

    /**
     * Every episode of a series, as Stremio "videos": one TMDb call per season, together (cached).
     *
     * @return list<array<string, mixed>>
     */
    private function episodes(int $showId, array $show): array
    {
        $paths = [];
        foreach (F::array($show, 'seasons') as $season) {
            if (is_array($season) && (int) ($season['episode_count'] ?? 0) > 0 && isset($season['season_number'])) {
                $paths[] = "tv/{$showId}/season/".(int) $season['season_number'];
            }
        }

        $videos = [];
        foreach ($this->tmdb->getMany($paths) as $season) {
            foreach (is_array($season) ? F::array($season, 'episodes') : [] as $episode) {
                if (! is_array($episode)) {
                    continue;
                }
                $s = (int) ($episode['season_number'] ?? 0);
                $e = (int) ($episode['episode_number'] ?? 0);
                $date = F::string($episode, 'air_date');
                $videos[] = self::withoutNulls([
                    'id' => "tmdb:{$showId}:{$s}:{$e}",
                    'title' => F::string($episode, 'name') ?? "Épisode {$e}",
                    'season' => $s,
                    'episode' => $e,
                    'released' => $date === null ? null : $date.'T00:00:00.000Z',
                    'overview' => F::string($episode, 'overview'),
                    'thumbnail' => F::image(F::string($episode, 'still_path'), 'w300'),
                ]);
            }
        }

        return $videos;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withoutNulls(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null);
    }
}
