<?php

namespace App\Services\Tmdb;

use App\Models\Profile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Applies a profile's age limit to TMDb data.
 *
 * Detail pages already contain their certification. For lists, ratings are fetched in
 * one batch (cached a week, shared by all accounts): first display of a list for a kid
 * profile is a bit slower, then instant.
 */
class AgeGate
{
    public function __construct(private TmdbClient $tmdb) {}

    /**
     * Deny a movie / TV show page (TMDb data with release_dates or content_ratings).
     *
     * @param  array<string, mixed>  $title
     */
    public function authorizeTitle(?Profile $profile, array $title): void
    {
        if ($profile?->isRestricted() && ! $profile->allowsAge(TmdbFormat::ageRating(TmdbFormat::certification($title)))) {
            throw new AccessDeniedHttpException(__('This content is not available on this profile.'));
        }
    }

    /** Deny pages of a TV show (seasons) the profile may not see. */
    public function authorizeShow(?Profile $profile, int $showId): void
    {
        if ($profile?->isRestricted() && ! $profile->allowsAge($this->ratings([['media_type' => 'tv', 'id' => $showId]])["tv:{$showId}"])) {
            throw new AccessDeniedHttpException(__('This content is not available on this profile.'));
        }
    }

    /**
     * Keep the movies / TV shows the profile may see (other items, e.g. people, are kept).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function filter(?Profile $profile, array $items): array
    {
        if (! $profile?->isRestricted() || $items === []) {
            return $items;
        }

        $ratings = $this->ratings($items);

        return array_values(array_filter($items, function (array $item) use ($profile, $ratings) {
            $key = self::key($item);

            return $key === null || $profile->allowsAge($ratings[$key] ?? null);
        }));
    }

    /**
     * Filter several lists with a single batch of rating lookups.
     *
     * @param  array<array-key, list<array<string, mixed>>>  $lists
     * @return array<array-key, list<array<string, mixed>>>
     */
    public function filterMany(?Profile $profile, array $lists): array
    {
        if (! $profile?->isRestricted()) {
            return $lists;
        }

        // Prefetch every rating at once, then filter each list from the cache.
        $this->ratings(array_merge([], ...array_values($lists)));

        return array_map(fn (array $items) => $this->filter($profile, $items), $lists);
    }

    /**
     * Age rating of each movie / TV show, keyed "movie:550" / "tv:1399" (null = unknown).
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int|null>
     */
    private function ratings(array $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            if ($key = self::key($item)) {
                [$type, $id] = explode(':', $key);
                $paths[$key] = $type === 'movie' ? "movie/{$id}/release_dates" : "tv/{$id}/content_ratings";
            }
        }

        $responses = $this->tmdb->getMany(array_values($paths), 'ratings');

        $ratings = [];
        foreach ($paths as $key => $path) {
            $response = $responses[$path] ?? null;
            $field = str_starts_with($key, 'movie:') ? 'release_dates' : 'content_ratings';
            $ratings[$key] = $response === null ? null : TmdbFormat::ageRating(TmdbFormat::certification([$field => $response]));
        }

        return $ratings;
    }

    /** @param  array<string, mixed>  $item */
    private static function key(array $item): ?string
    {
        $type = $item['media_type'] ?? null;

        return in_array($type, ['movie', 'tv'], true) && isset($item['id']) ? $type.':'.(int) $item['id'] : null;
    }
}
