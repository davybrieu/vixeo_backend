<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ResolveStreamRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Player page URL to resolve into a playable stream ("https://voe.sx/e/abc"). */
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
