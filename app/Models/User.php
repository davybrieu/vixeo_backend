<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'parental_pin'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'parental_pin' => 'hashed',
        ];
    }

    /** Parental control is on when a parental PIN is set. */
    public function hasParentalControl(): bool
    {
        return $this->parental_pin !== null;
    }

    /** Apps of the account that can receive push notifications. @return HasMany<PushDevice, $this> */
    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    /** On / off of the plug-ins this account does not own: the Vixeo catalog, those of everyone. */
    public function addonPreferences(): HasMany
    {
        return $this->hasMany(AddonPreference::class);
    }

    /** Plug-ins this account installed (docs/plugins.md). */
    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<Profile, $this> */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class)->orderBy('position')->orderBy('id');
    }
}
