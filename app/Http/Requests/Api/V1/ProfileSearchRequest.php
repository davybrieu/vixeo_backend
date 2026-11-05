<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ProfileSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The searched text (2 to 100 characters). */
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('query'))) {
            $this->merge(['query' => preg_replace('/\s+/u', ' ', trim($this->input('query')))]);
        }
    }

    public function searchQuery(): string
    {
        return (string) $this->validated('query');
    }
}
