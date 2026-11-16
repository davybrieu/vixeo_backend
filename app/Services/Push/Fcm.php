<?php

namespace App\Services\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Firebase Cloud Messaging, HTTP v1 API: the only place that sends push notifications
 * (docs/notifications.md). Signs in with the Firebase project's service account
 * (`services.fcm.credentials`): a JWT signed with its key is exchanged for an access token,
 * cached until shortly before it expires. Registration tokens are never logged.
 */
class Fcm
{
    /** FCM accepts many requests at once: they are sent by groups of this size. */
    private const CONCURRENCY = 20;

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @var array{project_id: string, client_email: string, private_key: string, token_uri: string}|false|null */
    private array|false|null $credentials = null;

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * Sends [$message] to each token. The answer, by token: sent, invalid (the app was
     * uninstalled or the token replaced: forget it) or failed (try again later).
     *
     * @param  list<string>  $tokens
     * @return array<string, PushResult>
     */
    public function send(array $tokens, PushMessage $message): array
    {
        $credentials = $this->credentials() ?? throw new RuntimeException('FCM is not configured.');
        $url = "https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send";
        $payload = $message->toFcm();

        $results = [];
        foreach (array_chunk(array_values(array_unique($tokens)), self::CONCURRENCY) as $chunk) {
            $results += $this->sendChunk($url, $chunk, $payload, retry: true);
        }

        return $results;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $payload
     * @return array<string, PushResult>
     */
    private function sendChunk(string $url, array $tokens, array $payload, bool $retry): array
    {
        try {
            $accessToken = $this->accessToken();
        } catch (Throwable $e) {
            Log::warning('FCM sign-in failed: '.$e->getMessage());

            return array_fill_keys($tokens, PushResult::Failed);
        }

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $token) => $pool->as($token)->withToken($accessToken)->acceptJson()->timeout(10)
                ->post($url, ['message' => ['token' => $token] + $payload]),
            $tokens,
        ));

        $results = [];
        $expired = [];
        foreach ($tokens as $token) {
            $response = $responses[$token] ?? null;
            if (! $response instanceof Response) {
                // Connection error.
                $results[$token] = PushResult::Failed;

                continue;
            }
            if ($response->status() === 401 && $retry) {
                $expired[] = $token;

                continue;
            }
            $results[$token] = self::result($response);
        }

        if ($expired !== []) {
            // Access token refused (revoked, clock): a new one, once.
            Cache::forget($this->cacheKey());
            $results += $this->sendChunk($url, $expired, $payload, retry: false);
        }

        return $results;
    }

    private static function result(Response $response): PushResult
    {
        if ($response->successful()) {
            return PushResult::Sent;
        }

        $error = (array) $response->json('error', []);
        $codes = [];
        foreach ((array) ($error['details'] ?? []) as $detail) {
            if (is_array($detail) && isset($detail['errorCode'])) {
                $codes[] = (string) $detail['errorCode'];
            }
        }
        $status = (string) ($error['status'] ?? '');
        $text = (string) ($error['message'] ?? '');

        if ($response->status() === 404 || in_array('UNREGISTERED', $codes, true) || in_array('SENDER_ID_MISMATCH', $codes, true)
            || ($response->status() === 400 && ($status === 'INVALID_ARGUMENT' || in_array('INVALID_ARGUMENT', $codes, true))
                && str_contains(strtolower($text), 'registration token'))) {
            return PushResult::Invalid;
        }

        Log::warning('FCM send failed', ['status' => $response->status(), 'error' => $status ?: null, 'codes' => $codes]);

        return PushResult::Failed;
    }

    /** OAuth access token of the service account, cached until 5 minutes before it expires. */
    private function accessToken(): string
    {
        $cached = Cache::get($this->cacheKey());
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials() ?? throw new RuntimeException('FCM is not configured.');
        $now = time();
        $jwt = self::jwt([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $credentials['private_key']);

        try {
            $response = Http::asForm()->acceptJson()->timeout(10)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Google sign-in unreachable.', previous: $e);
        }

        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Google refused the service account (HTTP '.$response->status().').');
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        Cache::put($this->cacheKey(), $token, $ttl);

        return $token;
    }

    /** @param  array<string, mixed>  $claims */
    private static function jwt(array $claims, string $privateKey): string
    {
        $encode = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $input = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode($claims));

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Invalid service account key.');
        }

        return $input.'.'.$encode($signature);
    }

    private function cacheKey(): string
    {
        return 'fcm:access-token:'.sha1((string) ($this->credentials()['client_email'] ?? ''));
    }

    /**
     * The service account: a path to its JSON file, or "base64:" and the JSON in base64.
     *
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null
     */
    private function credentials(): ?array
    {
        if ($this->credentials === null) {
            $this->credentials = self::readCredentials((string) config('services.fcm.credentials')) ?? false;
        }

        return $this->credentials === false ? null : $this->credentials;
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|null */
    private static function readCredentials(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'base64:')) {
            $json = base64_decode(substr($value, 7), true);
        } elseif (str_starts_with($value, '{')) {
            $json = $value;
        } else {
            $json = is_readable($value) ? file_get_contents($value) : false;
        }
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return null;
        }

        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (! is_string($data[$key] ?? null) || $data[$key] === '') {
                return null;
            }
        }

        return [
            'project_id' => $data['project_id'],
            'client_email' => $data['client_email'],
            'private_key' => $data['private_key'],
            'token_uri' => is_string($data['token_uri'] ?? null) && str_starts_with($data['token_uri'], 'https://')
                ? $data['token_uri']
                : 'https://oauth2.googleapis.com/token',
        ];
    }
}
