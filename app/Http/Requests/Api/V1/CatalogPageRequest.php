<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Tmdb\TmdbCatalog;
use Illuminate\Foundation\Http\FormRequest;

class CatalogPageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Page number (1 to 500). */
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.TmdbCatalog::MAX_PAGE],
        ];
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }
}
