<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogHomeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Only movies or only TV shows. Omit for a mixed home. */
            'type' => ['sometimes', Rule::enum(MediaType::class)],
        ];
    }
}
