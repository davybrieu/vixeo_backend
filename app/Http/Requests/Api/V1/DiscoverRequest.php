<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DiscoverSort;
use App\Http\Requests\Api\V1\Concerns\FiltersTitles;
use Illuminate\Validation\Rule;

class DiscoverRequest extends CatalogPageRequest
{
    use FiltersTitles;

    public function rules(): array
    {
        return parent::rules() + $this->titleFilterRules() + [
            /** Defaults to popularity. */
            'sort' => ['sometimes', Rule::enum(DiscoverSort::class)],
        ];
    }

    public function sort(): DiscoverSort
    {
        return $this->enum('sort', DiscoverSort::class) ?? DiscoverSort::Popularity;
    }
}
