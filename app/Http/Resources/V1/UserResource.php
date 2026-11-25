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
            /** False for an account created with Google or Apple that never chose a password: « Mon compte » offers to create one. */
            'has_password' => $this->hasPassword(),
            /**
             * Google and Apple accounts linked to this one (« Continuer avec… »).
             *
             * @var list<string>
             */
            'providers' => SocialAccount::providersOf($this->resource),
            /** A parental PIN is set (see /parental-control). */
            'parental_control' => $this->hasParentalControl(),
            'created_at' => $this->created_at,
        ];
    }
}
