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
        ];
    }

    public function sort(): PersonCreditsSort
    {
        return $this->enum('sort', PersonCreditsSort::class) ?? PersonCreditsSort::Popular;
    }
}
