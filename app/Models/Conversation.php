<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A conversation of the messaging (docs/messagerie.md): `direct` between two profiles (their ids
 * in `profile_low_id` < `profile_high_id`), or `group` (a name, no pair). Members, messages and
 * blocks are written only through App\Services\Messaging\Conversations.
 *
 * @property int $id
 * @property string $type
 * @property string|null $name
 * @property string $status
 * @property int|null $requested_by_profile_id
 * @property int|null $created_by_profile_id
 * @property int|null $profile_low_id
 * @property int|null $profile_high_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 */
class Conversation extends Model
{
    public const DIRECT = 'direct';

    public const GROUP = 'group';

    /** A direct conversation started by a profile, waiting for the other one to accept it. */
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    /** A declined request: hidden from both profiles. */
    public const REJECTED = 'rejected';

    /** Members of a group, its admin included. */
    public const MAX_GROUP_MEMBERS = 31;

    protected $table = 'direct_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function isGroup(): bool
    {
        return $this->type === self::GROUP;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** A request [profile] received and has not answered yet. */
    public function isRequestFor(Profile $profile): bool
    {
        return $this->isPending() && $this->requested_by_profile_id !== $profile->id;
    }

    /** @return HasMany<ConversationMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class, 'conversation_id');
    }

    /** Members who did not leave (or were not removed from) the group. */
    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    /** @return HasMany<DirectMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class, 'conversation_id');
    }

    /** @return HasOne<DirectMessage, $this> */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(DirectMessage::class, 'conversation_id')->latestOfMany();
    }

    /** @return HasOne<DirectMessage, $this> */
    public function firstMessage(): HasOne
    {
        return $this->hasOne(DirectMessage::class, 'conversation_id')->oldestOfMany();
    }

    /** @return BelongsTo<Profile, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'requested_by_profile_id');
    }
}
