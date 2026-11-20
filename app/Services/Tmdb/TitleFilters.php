<?php

namespace App\Services\Tmdb;

/**
 * Filters of the search page: genre and years. TMDb's text search has no such filters: the API
 * applies them itself to the results.
 */
final readonly class TitleFilters
{
    public function __construct(
        public ?int $genre = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->genre === null && $this->yearFrom === null && $this->yearTo === null;
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
