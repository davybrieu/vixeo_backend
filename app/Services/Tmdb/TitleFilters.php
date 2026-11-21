<?php

namespace App\Services\Tmdb;

/**
 * Filters of the search page: genre, years and minimum rating. TMDb's text search has no such
 * filters: the API applies them itself to the results.
 *
 * The rating of a title ([rating], « Note » filter and « Mieux notés » order, developer's request,
 * 21/11/2026): **TMDb's first**, IMDb's when TMDb's has too few votes to count.
 */
final readonly class TitleFilters
{
    /** TMDb votes for its rating to come before IMDb's. */
    public const MIN_TMDB_VOTES = 20;

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
     * The rating a title is filtered and sorted by: TMDb's with at least MIN_TMDB_VOTES votes, else
     * IMDb's (`imdb_rating`, set by TitleSummaries), else TMDb's with fewer votes; null when no one
     * rated it.
     *
     * @param  array<string, mixed>  $item
     */
    public static function rating(array $item): ?float
    {
        return self::rated($item)[0];
    }

    /**
     * « Mieux notés » order key, best first: titles whose rating has enough votes (TMDb's, or
     * IMDb's) before those rated by a few TMDb votes only (a 10/10 from 2 votes is not the best
     * title), then by rating; unrated titles last.
     *
     * @param  array<string, mixed>  $item
     * @return array{bool, float}
     */
    public static function ratingOrder(array $item): array
    {
        [$rating, $enoughVotes] = self::rated($item);

        return [$enoughVotes, $rating ?? -1.0];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{?float, bool} the rating, and whether enough votes back it
     */
    private static function rated(array $item): array
    {
        $tmdb = (float) ($item['vote_average'] ?? 0);
        $votes = (int) ($item['vote_count'] ?? 0);
        if ($tmdb > 0 && $votes >= self::MIN_TMDB_VOTES) {
            return [$tmdb, true];
        }
        $imdb = is_array($item['imdb_rating'] ?? null) ? ($item['imdb_rating']['rating'] ?? null) : null;
        if (is_numeric($imdb) && (float) $imdb > 0) {
            return [(float) $imdb, true];
        }

        return [$tmdb > 0 && $votes > 0 ? $tmdb : null, false];
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

        if ($this->minRating !== null && (self::rating($item) ?? -1) < $this->minRating) {
            return false;
        }

        return true;
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
