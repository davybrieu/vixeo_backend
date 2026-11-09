<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StreamSourcesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Resolve each link into a playable stream (slower, hits the scraper). */
            'extract' => ['sometimes', 'boolean'],
            /** Keep only the links of that provider. */
            'provider' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function extract(): bool
    {
        return $this->boolean('extract');
    }

    public function provider(): ?string
    {
        $value = $this->validated('provider');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
