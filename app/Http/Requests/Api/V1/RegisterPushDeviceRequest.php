<?php

namespace App\Http\Requests\Api\V1;

use App\Models\PushDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPushDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            /** `windows`: no Firebase, the notifications come by the real-time channel while the app runs. */
            'platform' => ['required', 'string', Rule::in(['android', 'ios', 'windows'])],
            /** Firebase installation ID of the app (firebase_messaging 17). Kept encrypted, never sent back. Not for Windows. */
            'fid' => ['exclude_if:platform,windows', 'required_without:token', 'string', 'max:4096'],
            /** Firebase registration token of the apps before the installation ID (still accepted). Not for Windows. */
            'token' => ['exclude_if:platform,windows', 'required_without:fid', 'string', 'max:4096'],
            /** New episodes of the « Ma liste » TV shows of the active profile (X-Profile-Id). */
            'new_episodes' => ['required', 'boolean'],
            /** Social activity of the active profile: likes of its reviews, followers, follow requests, messages (default true). */
            'social' => ['sometimes', 'boolean'],
            /** Device time minus UTC, in minutes (Paris in winter: 60): no episode notification at night. */
            'utc_offset' => ['sometimes', 'integer', 'between:-720,840'],
        ];
    }

    /** How the app receives its notifications: a PushDevice target. */
    public function target(): string
    {
        return match (true) {
            $this->input('platform') === 'windows' => PushDevice::REALTIME,
            $this->filled('fid') => PushDevice::FCM_FID,
            default => PushDevice::FCM_TOKEN,
        };
    }

    /** The Firebase installation ID or registration token (null for the real-time channel). */
    public function registration(): ?string
    {
        return match ($this->target()) {
            PushDevice::REALTIME => null,
            PushDevice::FCM_FID => $this->string('fid')->toString(),
            default => $this->string('token')->toString(),
        };
    }
}
