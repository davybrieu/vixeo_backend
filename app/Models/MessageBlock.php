<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A profile blocked by another one in the messaging: neither can write to the other, start a
 * conversation with the other nor add the other to a group.
 *
 * @property int $blocker_profile_id
 * @property int $blocked_profile_id
 */
class MessageBlock extends Model
{
    protected $table = 'direct_message_blocks';

    protected $guarded = [];

    /** @return BelongsTo<Profile, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocked_profile_id');
    }
}
