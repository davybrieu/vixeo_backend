<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TitleArtworkRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** A poster's `path` from `catalog/{type}/{id}/images`; null: TMDb's. Absent: unchanged. */
            'poster' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** A backdrop's `path`; null: TMDb's. Absent: unchanged. */
            'backdrop' => ['sometimes', 'nullable', 'string', 'max:100'],
            /** A logo's `path`; null: TMDb's. Absent: unchanged. */
            'logo' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
