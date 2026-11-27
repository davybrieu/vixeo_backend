<?php

it('generates an OpenAPI document covering the v1 routes', function () {
    $path = tempnam(sys_get_temp_dir(), 'openapi');

    $this->artisan('scramble:export', ['--path' => $path, '--fail-on-unknown' => true])->assertSuccessful();

    $spec = json_decode(file_get_contents($path), true);
    unlink($path);

    expect($spec['paths'])->toHaveKeys([
        '/v1/auth/register',
        '/v1/auth/email/link',
        '/v1/auth/email/consume',
        '/v1/auth/email/token',
        '/v1/auth/logout',
        '/v1/me',
        '/v1/profiles',
        '/v1/profiles/{profile}',
        '/v1/avatars',
        '/v1/auth/device',
        '/v1/auth/device/token',
        '/v1/auth/device/lookup',
        '/v1/auth/device/approve',
        '/v1/profiles/{profile}/sync',
        '/v1/catalog/home',
        '/v1/catalog/genres',
        '/v1/catalog/search',
        '/v1/catalog/search/{type}',
        '/v1/catalog/{type}/lists/{list}',
        '/v1/catalog/movie/{id}',
        '/v1/catalog/tv/{id}',
        '/v1/catalog/tv/{id}/season/{season}',
        '/v1/catalog/person/{id}',
    ]);
});

it('never documents secrets as query parameters (they would end up in server logs)', function () {
    $path = tempnam(sys_get_temp_dir(), 'openapi');
    $this->artisan('scramble:export', ['--path' => $path])->assertSuccessful();
    $spec = json_decode(file_get_contents($path), true);
    unlink($path);

    $leaks = [];
    foreach ($spec['paths'] as $url => $operations) {
        foreach ($operations as $method => $operation) {
            foreach ($operation['parameters'] ?? [] as $parameter) {
                // PKCE (« Continuer avec Google / Apple »): the hash of a secret the app keeps,
                // public by design (RFC 7636), in the address a browser opens.
                $public = in_array($parameter['name'], ['code_challenge', 'code_challenge_method'], true);
                if ($parameter['in'] === 'query' && ! $public && preg_match('/pin|password|token|secret|code/i', $parameter['name'])) {
                    $leaks[] = strtoupper($method)." {$url} ?{$parameter['name']}";
                }
            }
        }
    }

    expect($leaks)->toBe([]);
});
