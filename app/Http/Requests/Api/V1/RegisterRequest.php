<?php

namespace App\Http\Requests\Api\V1;

use App\Support\Usernames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => ['required', 'string', 'max:50'],
            'username' => [
                'sometimes', 'required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::notIn(Usernames::RESERVED), 'unique:profiles,username',
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            /** Free label identifying the device, e.g. "Pixel 8" or "Salon TV". */
            'redirect_uri' => ['required', 'string', 'max:255'],
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
        ];
    }
}
