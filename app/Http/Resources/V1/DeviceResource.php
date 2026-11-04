<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A signed-in device (one API token).
 *
 * @mixin PersonalAccessToken
 */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $this->expires_at ?? ($minutes > 0 ? $this->created_at?->copy()->addMinutes($minutes) : null);

        return [
            'id' => (int) $this->id,
            /** Device name given at sign-in, e.g. "Vixeo TV" or "iPhone". */
            'name' => (string) $this->name,
            /** The device making this request. */
            'current' => (int) $this->id === (int) $request->user()?->currentAccessToken()?->getKey(),
            'signed_in_at' => $this->created_at,
            'last_used_at' => $this->last_used_at,
            /** After this date the device has to sign in again. */
            'expires_at' => $expiresAt,
        ];
    }
}
