<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Services\Tmdb\TitleFilters;

/**
 * Filters of the search page (genre, years).
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
        ];
    }

    public function titleFilters(): TitleFilters
    {
        $validated = $this->validated();

        return new TitleFilters(
            genre: isset($validated['genre']) ? (int) $validated['genre'] : null,
            yearFrom: isset($validated['year_from']) ? (int) $validated['year_from'] : null,
            yearTo: isset($validated['year_to']) ? (int) $validated['year_to'] : null,
        );
    }
}
