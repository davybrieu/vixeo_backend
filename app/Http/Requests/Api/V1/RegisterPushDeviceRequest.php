<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPushDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** Firebase Cloud Messaging registration token of the app. Kept encrypted, never sent back. */
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            /** New episodes of the « Ma liste » TV shows of the active profile (X-Profile-Id). */
            'new_episodes' => ['required', 'boolean'],
            /** Social activity of the active profile: likes of its reviews, followers, follow requests (default true). */
            'social' => ['sometimes', 'boolean'],
            /** Device time minus UTC, in minutes (Paris in winter: 60): no episode notification at night. */
            'utc_offset' => ['sometimes', 'integer', 'between:-720,840'],
        ];
    }
}
