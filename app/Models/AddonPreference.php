<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An account's choice (on / off) for a plug-in it does not own: the Vixeo catalog (`vixeo`) or a
 * plug-in installed for every account (its number). Written only through
 * App\Services\Addons\Addons. See docs/plugins.md.
 */
class AddonPreference extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
