<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** The code the browser brought back to the app (`?code=`). */
            'code' => ['required', 'string', 'max:100'],
            /** The secret whose hash was sent as `code_challenge` (43 to 128 characters). */
            'code_verifier' => ['required', 'string', 'regex:/^[A-Za-z0-9._~-]{43,128}$/'],
            /** Free label identifying the device, e.g. "Pixel 8" or "PC du salon". */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
