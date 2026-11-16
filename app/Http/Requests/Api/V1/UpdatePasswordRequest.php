<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The password in use, to confirm. */
            'current_password' => ['required', 'string'],
            /** The new password. */
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
