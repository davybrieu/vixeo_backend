<?php

namespace App\Http\Resources\V1\Messages;

use App\Models\MessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A photo or a video of a message.
 *
 * @mixin MessageAttachment
 */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            /** Name of the file sent. */
            'name' => (string) $this->original_name,
            'mime_type' => (string) $this->mime_type,
            /** Size, in bytes. */
            'size' => (int) $this->size,
            /** Where to download it, under /api/v1, with the bearer token and the X-Profile-Id header. */
            'url' => '/messages/attachments/'.$this->id,
        ];
    }
}
