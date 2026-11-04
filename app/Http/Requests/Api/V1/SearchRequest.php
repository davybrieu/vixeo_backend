<?php

namespace App\Http\Requests\Api\V1;

class SearchRequest extends CatalogPageRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'query' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('query'))) {
            $this->merge(['query' => trim($this->input('query'))]);
        }
    }

    public function searchQuery(): string
    {
        return (string) $this->validated('query');
    }
}
