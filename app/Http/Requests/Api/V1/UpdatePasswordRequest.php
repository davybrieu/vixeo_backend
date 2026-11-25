<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The password in use, to confirm. Not asked of an account without a password (`has_password`): it creates its first one. */
            'current_password' => [$this->user()?->hasPassword() === false ? 'nullable' : 'required', 'string'],
            /** The new password. */
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
