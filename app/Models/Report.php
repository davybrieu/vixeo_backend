<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report of a profile, a review, a list, a comment or a message (docs/moderation.md), handled
 * with `php artisan vixeo:moderate`. Written only through App\Services\Social\Reports.
 */
class Report extends Model
{
    public const SUBJECTS = ['profile', 'review', 'list', 'comment', 'message'];

    public const REASONS = ['spam', 'harassment', 'hate', 'sexual', 'violence', 'spoiler', 'illegal', 'other'];

    public const OPEN = 'open';

    public const HIDDEN = 'hidden';

    public const DISMISSED = 'dismissed';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['subject_id' => 'integer', 'handled_at' => 'datetime'];
    }

    /** @return BelongsTo<Profile, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reporter_profile_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'author_profile_id');
    }
}
