<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Services\Tmdb\TitleFilters;

/**
 * Filters of the search page (genre, years, minimum rating, minimum number of voters).
 */
trait FiltersTitles
{
    /** @return array<string, list<string>> */
    protected function titleFilterRules(): array
    {
        return [
            /** TMDb genre id (see GET /catalog/genres). */
            'genre' => ['sometimes', 'integer', 'min:1'],
            /** Released (movie) or first aired (TV show) this year or later. */
            'year_from' => ['sometimes', 'integer', 'between:1870,2100'],
            /** Released (movie) or first aired (TV show) this year or earlier. */
            'year_to' => array_values(array_filter([
                'sometimes', 'integer', 'between:1870,2100', $this->has('year_from') ? 'gte:year_from' : null,
            ])),
            /** Minimum rating out of 10: IMDb's, else TMDb's (docs/notes.md). Unrated titles are left out. */
            'min_rating' => ['sometimes', 'numeric', 'between:0,10'],
            /** Minimum number of voters of that rating (IMDb's, else TMDb's). Unrated titles are left out. */
            'min_votes' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    public function titleFilters(): TitleFilters
    {
        $validated = $this->validated();

        return new TitleFilters(
            genre: isset($validated['genre']) ? (int) $validated['genre'] : null,
            yearFrom: isset($validated['year_from']) ? (int) $validated['year_from'] : null,
            yearTo: isset($validated['year_to']) ? (int) $validated['year_to'] : null,
            minRating: isset($validated['min_rating']) ? (float) $validated['min_rating'] : null,
            minVotes: isset($validated['min_votes']) ? (int) $validated['min_votes'] : null,
        );
    }
}
