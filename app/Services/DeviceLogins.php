<?php

namespace App\Services;

use App\Models\DeviceLogin;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signs a TV in from a phone (device authorization flow, as on streaming TVs):
 * the TV shows a QR code / short code, a signed-in person approves it, the TV polls
 * until it receives its own token.
 */
class DeviceLogins
{
    /** Codes live 10 minutes. */
    public const TTL_SECONDS = 600;

    /** The TV polls every 5 seconds. */
    public const INTERVAL_SECONDS = 5;

    /** No 0/O, 1/I/L: easy to read on a TV and to type on a phone. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @return array{login: DeviceLogin, device_code: string} */
    public function start(string $deviceName): array
    {
        $deviceCode = Str::random(48);

        $login = DeviceLogin::create([
            'device_code_hash' => hash('sha256', $deviceCode),
            'user_code' => $this->newUserCode(),
            'device_name' => $deviceName,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS),
        ]);

        return ['login' => $login, 'device_code' => $deviceCode];
    }

    /** Pending (not yet approved) login for a code typed or scanned on the phone. */
    public function findPending(string $userCode): DeviceLogin
    {
        $login = DeviceLogin::where('user_code', self::normalize($userCode))->first();

        if ($login === null || ! $login->isUsable() || $login->approved_at !== null) {
            throw ValidationException::withMessages(['user_code' => [__('This code is invalid or has expired.')]]);
        }

        return $login;
    }

    public function approve(User $user, string $userCode): DeviceLogin
    {
        $login = $this->findPending($userCode);
        $login->forceFill(['user_id' => $user->id, 'approved_at' => now()])->save();

        return $login;
    }

    /**
     * What the TV gets when it polls: still pending, expired, or a token (once).
     *
     * @return array{status: 'pending'|'expired'|'approved', token?: string, user?: User}
     */
    public function poll(string $deviceCode): array
    {
        $login = DeviceLogin::where('device_code_hash', hash('sha256', $deviceCode))->first();

        if ($login === null || ! $login->isUsable()) {
            return ['status' => 'expired'];
        }

        if ($login->approved_at === null || $login->user === null) {
            return ['status' => 'pending'];
        }

        // One token per approval: a second poll with the same code gets "expired".
        $consumed = DeviceLogin::whereKey($login->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        if ($consumed === 0) {
            return ['status' => 'expired'];
        }

        return [
            'status' => 'approved',
            'token' => $login->user->createToken($login->device_name)->plainTextToken,
            'user' => $login->user,
        ];
    }

    /** "k7f-29q" → "K7F29Q". */
    public static function normalize(string $userCode): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $userCode));
    }

    /** "K7F29Q" → "K7F-29Q". */
    public static function format(string $userCode): string
    {
        return substr($userCode, 0, 3).'-'.substr($userCode, 3);
    }

    private function newUserCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (DeviceLogin::where('user_code', $code)->exists());

        return $code;
    }
}
