<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            /** Free label identifying the device, e.g. "Pixel 8" or "Salon TV". */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
