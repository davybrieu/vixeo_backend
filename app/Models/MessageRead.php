<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message read by a member of its conversation (read receipts, unread counts).
 *
 * @property int $message_id
 * @property int $profile_id
 */
class MessageRead extends Model
{
    public $timestamps = false;

    protected $table = 'direct_message_reads';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
