<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SearchSort;
use App\Http\Requests\Api\V1\Concerns\FiltersTitles;
use Illuminate\Validation\Rule;

/** Browsing without a text (docs/notes.md): the filters and order of the search page. */
class DiscoverRequest extends CatalogPageRequest
{
    use FiltersTitles;

    public function rules(): array
    {
        return parent::rules() + $this->titleFilterRules() + [
            /** Defaults to popularity (no relevance without a text). */
            'sort' => ['sometimes', Rule::enum(SearchSort::class)->except([SearchSort::Relevance])],
        ];
    }

    public function sort(): SearchSort
    {
        return $this->enum('sort', SearchSort::class) ?? SearchSort::Popularity;
    }
}
