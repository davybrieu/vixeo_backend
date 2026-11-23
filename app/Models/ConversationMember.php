<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A profile in a conversation (docs/messagerie.md). `left_at`: left the group, or removed by an
 * admin; such a member no longer reads nor writes, and comes back as the same row when added again.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $profile_id
 * @property string $role
 * @property Carbon|null $left_at
 */
class ConversationMember extends Model
{
    public const ADMIN = 'admin';

    public const MEMBER = 'member';

    protected $table = 'direct_conversation_members';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ADMIN;
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
