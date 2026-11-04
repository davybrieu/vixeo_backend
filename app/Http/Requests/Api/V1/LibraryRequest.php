<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ItemType;
use App\Enums\LibrarySort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(ItemType::class)],
            /** Favorites only: true = watched only, false = not watched only. */
            'watched' => ['sometimes', 'boolean'],
            /** Favorites only. Defaults to `recent`. */
            'sort' => ['sometimes', Rule::enum(LibrarySort::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            /** Default 50, max 100. */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', 50);
    }
}
