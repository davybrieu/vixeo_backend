<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * One Laravel app, two hosts (config/vixeo.php): the API (api.vixeo.app) and the presentation
 * site (www.vixeo.app). Without configured domains (local), one host serves both: the API under
 * /api, the site everywhere else.
 */
final class Hosts
{
    public static function apiDomain(): ?string
    {
        return self::domain('api');
    }

    public static function siteDomain(): ?string
    {
        return self::domain('site');
    }

    /** API request: JSON errors, never the site's error pages. */
    public static function isApi(Request $request): bool
    {
        $api = self::apiDomain();

        return $request->is('api/*', 'api') || ($api !== null && strtolower($request->getHost()) === $api);
    }

    private static function domain(string $key): ?string
    {
        $domain = config("vixeo.domains.{$key}");

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }
}
