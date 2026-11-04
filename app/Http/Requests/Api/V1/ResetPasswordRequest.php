<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            /** The 6-digit code received by e-mail. */
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', Password::defaults()],
            /** Free label identifying the device, e.g. "Pixel 8" or "Salon TV". */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
