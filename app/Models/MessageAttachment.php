<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo or a video sent in a conversation (docs/messagerie.md), stored on a private disk and
 * served only to the conversation's members (`GET /messages/attachments/{id}`).
 *
 * @property int $id
 * @property int $message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 */
class MessageAttachment extends Model
{
    protected $table = 'direct_message_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /** @return BelongsTo<DirectMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(DirectMessage::class, 'message_id');
    }
}
