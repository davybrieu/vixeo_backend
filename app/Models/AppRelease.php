<?php

namespace App\Models;

use App\Enums\AppPlatform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A published version of the Android or iOS app (App\Services\AppUpdates).
 *
 * @property AppPlatform $platform
 * @property string $version
 * @property int $build
 * @property bool $required
 * @property ?string $notes
 * @property ?string $download_url APK (Android): the app downloads and installs it
 * @property ?string $sha256 SHA-256 of the APK, checked by the app before installing
 * @property Carbon $published_at
 */
#[Fillable(['platform', 'version', 'build', 'required', 'notes', 'download_url', 'sha256', 'published_at'])]
class AppRelease extends Model
{
    protected function casts(): array
    {
        return [
            'platform' => AppPlatform::class,
            'build' => 'integer',
            'required' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query, AppPlatform $platform): void
    {
        $query->where('platform', $platform)->where('published_at', '<=', now());
    }
}
