<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DiscoverSort;
use Illuminate\Validation\Rule;

class DiscoverRequest extends CatalogPageRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            /** TMDb genre id (see GET /catalog/genres). */
            'genre' => ['sometimes', 'integer', 'min:1'],
            /** Defaults to popularity. */
            'sort' => ['sometimes', Rule::enum(DiscoverSort::class)],
        ];
    }
}
