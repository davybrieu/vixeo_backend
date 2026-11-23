<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A profile hidden from another one's feed, still followed (« Masquer », docs/social.md). */
class ProfileMute extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
