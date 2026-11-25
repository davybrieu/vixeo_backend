<?php

namespace App\Support;

use RuntimeException;

/** The bits of JSON Web Tokens the sign-in with Google and Apple needs (App\Services\SocialLogin). */
final class Jwt
{
    /**
     * The claims of a JWT, without checking its signature: only for an ID token received straight
     * from the provider's token endpoint over TLS, which OpenID Connect allows (Core 1.0,
     * 3.1.3.7: the TLS server validation stands for the signature). Null when it isn't one.
     *
     * @return array<string, mixed>|null
     */
    public static function unverifiedClaims(mixed $token): ?array
    {
        if (! is_string($token) || substr_count($token, '.') !== 2) {
            return null;
        }

        $payload = base64_decode(strtr(explode('.', $token)[1], '-_', '+/'), true);
        $claims = is_string($payload) ? json_decode($payload, true) : null;

        return is_array($claims) ? $claims : null;
    }

    /**
     * A JWT signed with ES256 (ECDSA P-256 and SHA-256), like the client secret of « Sign in with
     * Apple »: [$pem] is the private key (a .p8 file of Apple), [$keyId] its identifier.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function es256(array $claims, string $pem, string $keyId): string
    {
        $input = self::base64Url((string) json_encode(['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT']))
            .'.'.self::base64Url((string) json_encode($claims));

        $key = openssl_pkey_get_private($pem);
        if ($key === false || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Invalid ES256 key.');
        }

        return $input.'.'.self::base64Url(self::rawSignature($der));
    }

    public static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * OpenSSL gives an ECDSA signature in DER (SEQUENCE of the INTEGERs r and s); a JWT wants r and
     * s side by side, 32 bytes each (RFC 7518, 3.4).
     */
    private static function rawSignature(string $der): string
    {
        $offset = 2;
        if ((ord($der[1] ?? "\0") & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $parts = [];
        foreach ([0, 1] as $_) {
            if (($der[$offset] ?? '') !== "\x02") {
                throw new RuntimeException('Invalid ECDSA signature.');
            }
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\0");
            if (strlen($integer) > 32) {
                throw new RuntimeException('Invalid ECDSA signature.');
            }
            $parts[] = str_pad($integer, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0].$parts[1];
    }
}
