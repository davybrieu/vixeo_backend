<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class FavoriteStreamRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'item_id' => ['required', 'string', 'max:191'],
            /** Stream chosen by the app inside the content ("frenchstream:<id>", a player URL…). */
            'stream_id' => ['required', 'string', 'max:191'],
            /** true = add to favorites (tried first), false = remove. */
            'favorite' => ['required', 'boolean'],
        ];
    }
}
