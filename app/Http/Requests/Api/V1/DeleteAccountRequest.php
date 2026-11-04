<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The account password, to confirm. */
            'password' => ['required', 'string'],
        ];
    }
}
