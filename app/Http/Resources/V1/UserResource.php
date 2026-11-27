<?php

namespace App\Http\Resources\V1;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            /**
             * Google and Apple accounts linked to this one (« Continuer avec… »).
             *
             * @var list<string>
             */
            'providers' => SocialAccount::providersOf($this->resource),
            'created_at' => $this->created_at,
        ];
    }
}
