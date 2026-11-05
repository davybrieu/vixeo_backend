<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SearchSort;
use App\Http\Requests\Api\V1\Concerns\FiltersTitles;
use Illuminate\Validation\Rule;

class SearchRequest extends CatalogPageRequest
{
    use FiltersTitles;

    public function rules(): array
    {
        return parent::rules() + [
            'query' => ['required', 'string', 'max:100'],
        ] + $this->titleFilterRules() + [
            /** Defaults to relevance (TMDb order). Another order, or a filter, sorts the first 60 results. */
            'sort' => ['sometimes', Rule::enum(SearchSort::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('query'))) {
            $this->merge(['query' => trim($this->input('query'))]);
        }
    }

    public function searchQuery(): string
    {
        return (string) $this->validated('query');
    }

    public function sort(): SearchSort
    {
        return $this->enum('sort', SearchSort::class) ?? SearchSort::Relevance;
    }
}
