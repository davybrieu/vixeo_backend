<?php

namespace App\Http\Controllers;

use App\Services\LatestAndroidRelease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SiteController extends Controller
{
    /** Pages listed in the sitemap, with their priority. */
    private const PAGES = ['/' => '1.0', '/mentions-legales' => '0.2', '/confidentialite' => '0.2'];

    public function home(LatestAndroidRelease $release): InertiaResponse
    {
        return Inertia::render('Home', ['latestAndroid' => $release->get()]);
    }

    /** Short link to type on a TV (www.vixeo.app/apk): the current APK. */
    public function apk(LatestAndroidRelease $release): RedirectResponse
    {
        $apk = $release->apk();
        abort_if($apk === null, 404);

        return redirect()->away($apk['url'], 302);
    }

    public function legal(): InertiaResponse
    {
        $filled = fn (array $values) => array_filter($values, fn ($v) => is_string($v) && trim($v) !== '');

        return Inertia::render('Legal', [
            'publisher' => $filled(config('vixeo.publisher')),
            'host' => $filled(config('vixeo.host')),
        ]);
    }

    public function privacy(): InertiaResponse
    {
        return Inertia::render('Privacy');
    }

    public function sitemap(): Response
    {
        $base = self::siteUrl();
        $urls = collect(self::PAGES)->map(fn ($priority, $path) => sprintf(
            '<url><loc>%s</loc><priority>%s</priority></url>',
            e($base.($path === '/' ? '/' : $path)),
            $priority,
        ))->implode('');

        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>',
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
        );
    }

    public function robots(): Response
    {
        $base = self::siteUrl();

        return response("User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** vixeo.app (SITE_REDIRECT_DOMAINS) → the same page on www.vixeo.app. */
    public function redirectToSite(Request $request): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->away(self::siteUrl().'/'.ltrim($request->path(), '/').($query ? '?'.$query : ''), 301);
    }

    /** Public address of the site (SITE_URL), without trailing slash. */
    public static function siteUrl(): string
    {
        return rtrim((string) config('app.site_url'), '/');
    }
}
