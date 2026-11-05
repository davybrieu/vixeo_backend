<?php

namespace App\Services\Tmdb;

use App\Enums\MediaType;

/**
 * Filters of the search page ("Explorer" and text search): genre, years, minimum rating.
 *
 * Explorer sends them to TMDb (discover); a text search applies them itself to the results,
 * since TMDb's search has no such filters.
 */
final readonly class TitleFilters
{
    /** Votes needed for a rating to count (minimum rating, "best rated" order). */
    public const MIN_VOTES = 50;

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
     * TMDb discover parameters.
     *
     * @return array<string, int|float|string>
     */
    public function discoverQuery(MediaType $type): array
    {
        $date = $type === MediaType::Movie ? 'primary_release_date' : 'first_air_date';
        $query = [];

        if ($this->genre !== null) {
            $query['with_genres'] = $this->genre;
        }
        if ($this->yearFrom !== null) {
            $query["{$date}.gte"] = sprintf('%04d-01-01', $this->yearFrom);
        }
        if ($this->yearTo !== null) {
            $query["{$date}.lte"] = sprintf('%04d-12-31', $this->yearTo);
        }
        if ($this->minRating !== null) {
            $query['vote_average.gte'] = $this->minRating;
            $query['vote_count.gte'] = self::MIN_VOTES;
        }

        return $query;
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

        if ($this->minRating !== null) {
            if ((int) ($item['vote_count'] ?? 0) < self::MIN_VOTES || (float) ($item['vote_average'] ?? 0) < $this->minRating) {
                return false;
            }
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
