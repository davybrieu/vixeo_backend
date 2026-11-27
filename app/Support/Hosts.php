<?php

namespace App\Support;

use Illuminate\Http\Request;

/** The Laravel application is an API; all requests use JSON error responses. */
final class Hosts
{
    public static function apiDomain(): ?string
    {
        return self::domain('api');
    }

    /** API request: JSON errors, never the site's error pages. */
    public static function isApi(Request $request): bool
    {
        $api = self::apiDomain();

        return true;
    }

    private static function domain(string $key): ?string
    {
        $domain = config("vixeo.domains.{$key}");

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }
}
