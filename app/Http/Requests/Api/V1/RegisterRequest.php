<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge(['username' => mb_strtolower((string) $this->input('username'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],\n            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:profiles,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            /** Free label identifying the device, e.g. "Pixel 8" or "Salon TV". */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
