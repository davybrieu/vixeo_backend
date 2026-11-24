<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PersonCreditsSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonCreditsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** `popular` (most voted first, « Connu pour ») or `date` (newest first, « Filmographie »). */
            'sort' => ['sometimes', Rule::enum(PersonCreditsSort::class)],
            /** Page number: 20 titles a page. */
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function sort(): PersonCreditsSort
    {
        return $this->enum('sort', PersonCreditsSort::class) ?? PersonCreditsSort::Popular;
    }
}
