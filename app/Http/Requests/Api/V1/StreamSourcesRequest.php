<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StreamSourcesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Only the streams of that plug-in (`addon:12`); all of them when left out. */
            'provider' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function provider(): ?string
    {
        $value = $this->validated('provider');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
