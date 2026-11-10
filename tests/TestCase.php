<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Relative URLs ("/api/v1/...") go to the API host (API_URL), not to APP_URL (the site):
     * site tests use absolute URLs (site() in tests/Feature/Site/PagesTest.php).
     */
    protected function prepareUrlForRequest($uri)
    {
        $uri = (string) $uri;
        if (! preg_match('#^https?://#i', $uri)) {
            $uri = config('vixeo.urls.api').'/'.ltrim($uri, '/');
        }

        return parent::prepareUrlForRequest($uri);
    }
}
