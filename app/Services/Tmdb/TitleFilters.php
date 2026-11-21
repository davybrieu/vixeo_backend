<?php

namespace App\Services\Tmdb;

/**
 * Filters of the search page: genre, years, minimum rating and minimum number of voters. TMDb's
 * text search has no such filters: the API applies them itself to the results.
 *
 * The rating of a title ([rating], « Note » filter and « Mieux notés » order, developer's request,
 * 21/11/2026): **IMDb's, else TMDb's**, the one the apps show on the cards; its voters ([votes],
 * « Votants » filter) are those of the same site.
 */
final readonly class TitleFilters
{
    public function __construct(
        public ?int $genre = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?float $minRating = null,
        public ?int $minVotes = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->genre === null && $this->yearFrom === null && $this->yearTo === null
            && $this->minRating === null && $this->minVotes === null;
    }

    /** A minimum the API checks itself on each title (rating or voters): it needs IMDb's. */
    public function hasMinimums(): bool
    {
        return $this->minRating !== null || $this->minVotes !== null;
    }

    /**
     * The rating a title is filtered and sorted by: IMDb's (`imdb_rating`, set by TitleSummaries),
     * else TMDb's; null when neither rates it.
     *
     * @param  array<string, mixed>  $item
     */
    public static function rating(array $item): ?float
    {
        return self::shown($item)[0] ?? null;
    }

    /**
     * Voters of the rating a title shows (IMDb's, else TMDb's); 0 when neither rates it.
     *
     * @param  array<string, mixed>  $item
     */
    public static function votes(array $item): int
    {
        return self::shown($item)[1] ?? 0;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{float, int}|null the rating shown and its voters
     */
    private static function shown(array $item): ?array
    {
        $imdb = is_array($item['imdb_rating'] ?? null) ? $item['imdb_rating'] : [];
        if (is_numeric($imdb['rating'] ?? null) && (float) $imdb['rating'] > 0) {
            return [(float) $imdb['rating'], (int) ($imdb['votes'] ?? 0)];
        }
        $tmdb = (float) ($item['vote_average'] ?? 0);
        $votes = (int) ($item['vote_count'] ?? 0);

        return $tmdb > 0 && $votes > 0 ? [$tmdb, $votes] : null;
    }

    /**
     * Whether a TMDb movie / TV show matches (text search results).
     *
     * @param  array<string, mixed>  $item
     */
    public function matches(array $item): bool
    {
        if ($this->genre !== null && ! in_array($this->genre, array_map('intval', (array) ($item['genre_ids'] ?? [])), true)) {
            return false;
        }

        if ($this->yearFrom !== null || $this->yearTo !== null) {
            $year = TmdbFormat::year(self::date($item));
            if ($year === null
                || ($this->yearFrom !== null && $year < $this->yearFrom)
                || ($this->yearTo !== null && $year > $this->yearTo)) {
                return false;
            }
        }

        return $this->reachesMinimums($item);
    }

    /**
     * Whether a title's rating (IMDb's, else TMDb's) and its voters reach the minimums; true
     * without any.
     *
     * @param  array<string, mixed>  $item
     */
    public function reachesMinimums(array $item): bool
    {
        return ($this->minRating === null || (self::rating($item) ?? -1) >= $this->minRating)
            && ($this->minVotes === null || self::votes($item) >= $this->minVotes);
    }

    /**
     * Release date (movie) or first air date (TV show), or null.
     *
     * @param  array<string, mixed>  $item
     */
    public static function date(array $item): ?string
    {
        $date = $item[($item['media_type'] ?? null) === 'tv' ? 'first_air_date' : 'release_date'] ?? null;

        return is_string($date) && $date !== '' ? $date : null;
    }
}
