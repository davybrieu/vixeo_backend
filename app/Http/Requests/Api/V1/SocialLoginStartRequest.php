<?php

namespace App\Http\Requests\Api\V1;

use App\Services\SocialLogin\SocialLogins;
use Illuminate\Foundation\Http\FormRequest;

class SocialLoginStartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /**
             * Where the browser comes back with the code: `app.vixeo://auth` (Android, iOS) or
             * `http://127.0.0.1:{port}/auth` (Windows, a server of the app on the device).
             */
            'redirect_uri' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, \Closure $fail) {
                if (! is_string($value) || ! SocialLogins::validRedirect($value)) {
                    $fail(__('This return address is not allowed.'));
                }
            }],
            /** PKCE (RFC 7636): base64url of the SHA-256 of a secret the app keeps (`code_verifier`). */
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            /** Only `S256`. */
            'code_challenge_method' => ['sometimes', 'string', 'in:S256'],
        ];
    }
}
