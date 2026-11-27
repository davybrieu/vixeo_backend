<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A new Vixeo account and its first profile: the sign-up with an address and a password
 * (`auth/register`), or the first « Continuer avec Google / Apple » (SocialLogin\SocialLogins).
 */
final class Accounts
{
    public function __construct(
        private readonly AvatarCatalog $avatars,
        private readonly CoverCatalog $covers,
    ) {}

    /**
     * Creates the account and a first profile named after it, with an avatar and a cover of the
     * catalogs at random.
     */
    public function create(string $name, string $email, ?string $username = null, bool $emailVerified = false): User
    {
        return DB::transaction(function () use ($name, $email, $password, $username, $emailVerified) {
            $user = new User(['name' => $name, 'email' => $email]);
            $user->forceFill([
                'email_verified_at' => $emailVerified ? now() : null,
            ])->save();

            $user->profiles()->create([
                'name' => $user->name,
                'username' => $username ?? self::uniqueUsername($user->name),
                'avatar' => $this->avatars->randomId(),
                'cover' => $this->covers->randomId(),
            ]);

            return $user;
        });
    }

    /** A free username made from [$name]: « Léa Martin » → `lea_martin_x7k2qd`. */
    public static function uniqueUsername(string $name): string
    {
        $base = Str::limit(Str::lower(Str::slug($name, '_')) ?: 'membre', 22, '');
        do {
            $username = $base.'_'.Str::lower(Str::random(6));
        } while (Profile::query()->where('username', $username)->exists());

        return $username;
    }
}
