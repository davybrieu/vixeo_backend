<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\RatedType;
use App\Services\Tmdb\TmdbCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RatedListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Movies, TV shows or episodes. */
            'type' => ['required', Rule::enum(RatedType::class)],
            /** Page number (1 to 500), 20 ratings per page. */
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.TmdbCatalog::MAX_PAGE],
        ];
    }

    public function type(): RatedType
    {
        return RatedType::from((string) $this->validated('type'));
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }
}
