<?php

namespace App\Services\Tmdb;

/**
 * Filters of the search page: genre, years and minimum rating. TMDb's text search has no such
 * filters: the API applies them itself to the results.
 *
 * The rating of a title ([rating], « Note » filter and « Mieux notés » order, developer's request,
 * 21/11/2026): **IMDb's, else TMDb's**, the one the apps show on the cards.
 */
final readonly class TitleFilters
{
    public function __construct(
        public ?int $genre = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?float $minRating = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->genre === null && $this->yearFrom === null && $this->yearTo === null && $this->minRating === null;
    }

    /**
     * The rating a title is filtered and sorted by: IMDb's (`imdb_rating`, set by TitleSummaries),
     * else TMDb's; null when neither rates it.
     *
     * @param  array<string, mixed>  $item
     */
    public static function rating(array $item): ?float
    {
        $imdb = is_array($item['imdb_rating'] ?? null) ? ($item['imdb_rating']['rating'] ?? null) : null;
        if (is_numeric($imdb) && (float) $imdb > 0) {
            return (float) $imdb;
        }
        $tmdb = (float) ($item['vote_average'] ?? 0);

        return $tmdb > 0 && (int) ($item['vote_count'] ?? 0) > 0 ? $tmdb : null;
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

        return $this->reachesMinRating($item);
    }

    /**
     * Whether a title's rating (IMDb's, else TMDb's) reaches the minimum; true without one.
     *
     * @param  array<string, mixed>  $item
     */
    public function reachesMinRating(array $item): bool
    {
        return $this->minRating === null || (self::rating($item) ?? -1) >= $this->minRating;
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
