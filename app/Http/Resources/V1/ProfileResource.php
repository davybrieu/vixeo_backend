<?php

namespace App\Http\Resources\V1;

use App\Models\Profile;
use App\Support\SocialLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Profile */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'bio' => $this->bio,
            /** Private account: its lists and reviews are seen by its accepted followers only. */
            'is_private' => (bool) $this->is_private,
            /**
             * Accounts on social networks, in the order of the profile page.
             *
             * @var list<array{network: string, label: string, handle: string, url: string}>
             */
            'social_links' => SocialLinks::present($this->social_links),
            /** Avatar id (GET /avatars), null = initial letter. */
            'avatar' => $this->avatar,
            /** Its own photo (POST /profiles/{id}/photo), shown instead of the avatar; null = the avatar. */
            'photo_url' => $this->photoUrl(),
            /** Cover id (GET /covers): the banner of its page. */
            'cover' => $this->cover,
            /** Its own cover (POST /profiles/{id}/cover), shown instead of `cover`; null = `cover`. */
            'cover_url' => $this->coverUrl(),
            'position' => $this->position,
            'onboarded' => $this->onboarded_at !== null,
            'registration_completed' => $this->registration_completed_at !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
