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
