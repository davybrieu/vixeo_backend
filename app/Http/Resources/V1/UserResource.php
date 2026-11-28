<?php

namespace App\Http\Resources\V1;

use App\Models\Profile;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = Profile::query()->find($this->id);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'providers' => SocialAccount::providersOf($this->resource),
            // The account row is also its only profile; no separate profile lookup is needed by clients.
            'profile' => $profile === null ? null : ProfileResource::make($profile),
            'created_at' => $this->created_at,
        ];
    }
}
