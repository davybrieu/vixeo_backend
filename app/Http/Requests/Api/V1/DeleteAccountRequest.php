<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The account password, to confirm. Not asked of an account without a password (`has_password`: Google, Apple). */
            'password' => [$this->user()?->hasPassword() === false ? 'nullable' : 'required', 'string'],
        ];
    }
}
