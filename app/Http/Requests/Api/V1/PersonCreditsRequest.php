<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PersonCreditsType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonCreditsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Media type: movie or tv. */
            'type' => ['sometimes', Rule::enum(PersonCreditsType::class)],
            /** Page number: 20 titles a page. */
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function mediaType(): PersonCreditsType
    {
        return $this->enum('type', PersonCreditsType::class) ?? PersonCreditsType::Movie;
    }
}
