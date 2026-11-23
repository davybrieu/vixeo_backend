<?php

namespace App\Services\Tmdb;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Cache as CacheFactory;

/**
 * Fills the cache of the home before anyone asks for it (`catalog:warm`, every 30 minutes;
 * developer's request, 23/11/2026). Without it, the first person to open the app after an hour
 * (lists) or a day (title details) waited for about 180 TMDb requests (~4 s).
 *
 * At each run, the lists and rows of the home are asked to TMDb again ([TmdbClient::refreshing]):
 * they expire after an hour, so they never do. The details of the titles shown with their logo
 * and summary (carousel, curated rows: [TmdbCatalog::summarized]) are fetched when missing, and
 * all asked again every 12 hours (they expire after a day). When TMDb fails, the cached answers
 * stay. Not prepared: the age ratings of restricted profiles (AgeGate) and the titles of the
 * light rows (the app completes them with `GET /catalog/titles`).
 */
class CatalogWarmer
{
    /** The details are asked to TMDb again at least this often: less than their cache duration. */
    public const DETAILS_REFRESH = 12 * 3600;

    private const DETAILS_REFRESHED_KEY = 'catalog-warm:details-refreshed';

    private Cache $cache;

    public function __construct(private TmdbClient $tmdb)
    {
        // Same store as TMDb's answers: emptied with them.
        $this->cache = CacheFactory::store(config('services.tmdb.cache_store'));
    }

    /**
     * @param  bool  $refreshDetails  Ask the details again even if they were less than 12 hours ago.
     * @return array{rows: int, titles: int, details_refreshed: bool}
     */
    public function warm(bool $refreshDetails = false): array
    {
        $refreshing = $this->tmdb->refreshing();
        $home = app()->make(TmdbCatalog::class, ['tmdb' => $refreshing])->home(null);

        // The lists given with their summary, as CatalogController::home does (no profile here:
        // an age limit only removes titles).
        $lists = ['featured' => $home['featured']];
        foreach ($home['sections'] as $index => $section) {
            if (TmdbCatalog::summarized($home, $index)) {
                $lists[$index] = $section['items'];
            }
        }

        $refreshDetails = $refreshDetails || ! $this->cache->has(self::DETAILS_REFRESHED_KEY);
        app()->make(TitleSummaries::class, ['tmdb' => $refreshDetails ? $refreshing : $this->tmdb])->addMany($lists);
        if ($refreshDetails) {
            $this->cache->put(self::DETAILS_REFRESHED_KEY, now()->getTimestamp(), self::DETAILS_REFRESH);
        }

        $titles = [];
        foreach ($lists as $items) {
            foreach ($items as $item) {
                if (in_array($item['media_type'] ?? null, ['movie', 'tv'], true) && isset($item['id'])) {
                    $titles["{$item['media_type']}:{$item['id']}"] = true;
                }
            }
        }

        return [
            'rows' => count($home['sections']),
            'titles' => count($titles),
            'details_refreshed' => $refreshDetails,
        ];
    }
}
