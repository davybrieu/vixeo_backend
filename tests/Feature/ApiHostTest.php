<?php

/*
| The API host: JSON responses only.
*/

it('describes the API at the root of its host', function () {
    $this->get('/')->assertOk()->assertJson(['api' => 'https://api.vixeo.test/api/v1']);
});

it('answers JSON in French on an unknown path, even for a browser', function () {
    $this->get('/installer', ['Accept' => 'text/html'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Ressource introuvable.']);

    $this->get('/api/v1/nexiste-pas', ['Accept' => 'text/html'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Ressource introuvable.']);
});

it('keeps search engines away from the API', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
});

it('returns JSON for paths outside the API', function () {
    $this->get('/faq')->assertNotFound()->assertHeader('Content-Type', 'application/json');
});

it('keeps the 401 of the API in JSON for a browser', function () {
    $this->get('/api/v1/me', ['Accept' => 'text/html'])
        ->assertUnauthorized()
        ->assertExactJson(['message' => __('Unauthenticated.')]);
});
