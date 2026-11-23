<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A message of a conversation (docs/messagerie.md): text, or an empty body with an attachment.
 * Deleted by its sender for everybody: the row stays (`deleted_at`, empty body, files removed) so
 * the conversation shows « Ce message a été supprimé. » at its place.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_profile_id
 * @property string $body
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 */
class DirectMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'sender_profile_id');
    }

    /** @return HasMany<MessageAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class, 'message_id');
    }

    /** @return HasMany<MessageRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'message_id');
    }
}
