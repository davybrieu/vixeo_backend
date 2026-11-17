<?php

namespace App\Models;

use App\Services\Addons\AddonManifest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plug-in (add-on in the Stremio format) installed by an account, or for every account when
 * `user_id` is null. Written only through App\Services\Addons\Addons. See docs/plugins.md.
 *
 * @property string $transport_url
 * @property array<string, mixed> $manifest
 */
class Addon extends Model
{
    protected $guarded = [];

    protected $hidden = ['transport_url', 'url_hash'];

    protected function casts(): array
    {
        return [
            'transport_url' => 'encrypted',
            'manifest' => 'array',
            'enabled' => 'boolean',
            'position' => 'integer',
            'manifest_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The plug-ins an account sees: its own and those installed for everyone. */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('user_id')
            ->when($user !== null, fn (Builder $q) => $q->orWhere('user_id', $user->id)))
            // Those of the server first, then the account's, each in their order.
            ->orderByRaw('case when user_id is null then 0 else 1 end')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function parsedManifest(): AddonManifest
    {
        return AddonManifest::fromArray($this->manifest);
    }

    /** Address of the plug-in, without `/manifest.json`: resources are read under it. */
    public function baseUrl(): string
    {
        return AddonManifest::baseUrl($this->transport_url);
    }

    /** Host only: what the apps and the logs see of the address. */
    public function host(): string
    {
        return (string) parse_url($this->transport_url, PHP_URL_HOST);
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', $url);
    }
}
