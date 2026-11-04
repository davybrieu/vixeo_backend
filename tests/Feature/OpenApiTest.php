<?php

it('generates an OpenAPI document covering the v1 routes', function () {
    $path = tempnam(sys_get_temp_dir(), 'openapi');

    $this->artisan('scramble:export', ['--path' => $path, '--fail-on-unknown' => true])->assertSuccessful();

    $spec = json_decode(file_get_contents($path), true);
    unlink($path);

    expect($spec['paths'])->toHaveKeys([
        '/v1/auth/register',
        '/v1/auth/login',
        '/v1/auth/logout',
        '/v1/me',
        '/v1/profiles',
        '/v1/profiles/{profile}',
        '/v1/profiles/{profile}/verify-pin',
        '/v1/avatars',
        '/v1/auth/device',
        '/v1/auth/device/token',
        '/v1/auth/device/lookup',
        '/v1/auth/device/approve',
        '/v1/parental-control',
        '/v1/parental-control/pin',
        '/v1/parental-control/disable',
        '/v1/parental-control/verify',
        '/v1/profiles/{profile}/sync',
        '/v1/profiles/{profile}/continue-watching',
        '/v1/profiles/{profile}/favorites',
        '/v1/profiles/{profile}/history',
        '/v1/catalog/config',
        '/v1/catalog/home',
        '/v1/catalog/genres',
        '/v1/catalog/search',
        '/v1/catalog/search/{type}',
        '/v1/catalog/{type}/lists/{list}',
        '/v1/catalog/{type}/discover',
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
                if ($parameter['in'] === 'query' && preg_match('/pin|password|token|secret|code/i', $parameter['name'])) {
                    $leaks[] = strtoupper($method)." {$url} ?{$parameter['name']}";
                }
            }
        }
    }

    expect($leaks)->toBe([]);
});
