<?php

namespace App\Http\Resources\V1\Messages;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A profile in the messaging: who it is.
 *
 * @mixin Profile
 */
class ChatProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'username' => (string) $this->username,
            'name' => (string) $this->name,
            /** Id of its avatar in the catalog (GET /avatars). */
            'avatar' => $this->avatar,
        ];
    }
}
