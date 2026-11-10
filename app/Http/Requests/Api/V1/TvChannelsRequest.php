<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TvChannelsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Category returned by GET /tv/categories. */
            'category_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** Filter by channel name. */
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function categoryId(): ?string
    {
        $value = $this->validated('category_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function search(): string
    {
        return trim((string) $this->validated('q', ''));
    }
}
