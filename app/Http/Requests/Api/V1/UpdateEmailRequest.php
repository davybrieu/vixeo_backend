<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The new address. */
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            /** The account password, to confirm. An account without a password (`has_password`) creates one first. */
            'password' => [$this->user()?->hasPassword() === false ? 'nullable' : 'required', 'string'],
        ];
    }
}
