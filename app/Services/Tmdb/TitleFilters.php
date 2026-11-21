<?php

namespace App\Services\Tmdb;

/**
 * Filters of the search page: genre, years, rating and number of voters, each a range whose ends
 * are optional (sliders of the apps, developer's request, 21/11/2026). TMDb's text search has no
 * such filters: the API applies them itself to the results.
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
        public ?float $maxRating = null,
        public ?int $maxVotes = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->genre === null && $this->yearFrom === null && $this->yearTo === null
            && ! $this->hasRatingBounds();
    }

    /** A bound the API checks itself on each title (rating or voters): it needs IMDb's. */
    public function hasRatingBounds(): bool
    {
        return $this->minRating !== null || $this->maxRating !== null
            || $this->minVotes !== null || $this->maxVotes !== null;
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

        return $this->withinRatingBounds($item);
    }

    /**
     * Whether a title's rating (IMDb's, else TMDb's) and its voters are within the bounds; true
     * without any, false for an unrated title with one.
     *
     * @param  array<string, mixed>  $item
     */
    public function withinRatingBounds(array $item): bool
    {
        if (! $this->hasRatingBounds()) {
            return true;
        }
        $shown = self::shown($item);
        if ($shown === null) {
            return false;
        }
        [$rating, $votes] = $shown;

        return ($this->minRating === null || $rating >= $this->minRating)
            && ($this->maxRating === null || $rating <= $this->maxRating)
            && ($this->minVotes === null || $votes >= $this->minVotes)
            && ($this->maxVotes === null || $votes <= $this->maxVotes);
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
