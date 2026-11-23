<?php

namespace App\Http\Controllers;

use App\Enums\AppPlatform;
use App\Models\Profile;
use App\Services\AvatarCatalog;
use App\Services\LatestReleases;
use App\Support\SocialLinks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SiteController extends Controller
{
    /** Pages listed in the sitemap, with their priority. */
    private const PAGES = ['/' => '1.0', '/mentions-legales' => '0.2', '/confidentialite' => '0.2'];

    public function home(LatestReleases $releases): InertiaResponse
    {
        return Inertia::render('Home', [
            'latestAndroid' => $releases->get(AppPlatform::Android),
            'latestWindows' => $releases->get(AppPlatform::Windows),
        ]);
    }

    /** Short link to type on a TV (www.vixeo.app/apk): the current APK. */
    public function apk(LatestReleases $releases): RedirectResponse
    {
        $apk = $releases->apk();
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

    /**
     * Public page of a profile (www.vixeo.app/pseudo, like Twitter), the link shared from the app: name,
     *
     * @pseudo, avatar and biography, then « Ouvrir dans Vixeo » (Android) and « Télécharger ».
     * Adult profiles with a username only, as in the app; not indexed by search engines.
     */
    public function member(Request $request, string $username, AvatarCatalog $avatars): SymfonyResponse
    {
        $profile = Profile::query()
            ->where('username', mb_strtolower($username))
            ->where('is_kid', false)
            ->firstOrFail();
        $avatar = $avatars->find($profile->avatar);
        $address = self::memberUrl($profile->username);

        return Inertia::render('Member', [
            'member' => [
                'name' => $profile->name,
                'username' => $profile->username,
                'bio' => $profile->bio,
                // Private account (docs/social.md): its lists and reviews are for its followers.
                'isPrivate' => (bool) $profile->is_private,
                // Its social networks: links only (nothing loaded from them).
                'links' => array_map(fn (array $link) => [
                    'label' => $link['label'],
                    'handle' => $link['handle'],
                    'url' => $link['url'],
                ], SocialLinks::present($profile->social_links)),
                // Its own photo (from the API: api.vixeo.app), shown instead of the avatar.
                'photoUrl' => $profile->photoUrl(),
                'avatar' => $avatar === null ? null : [
                    'background' => $avatar['background'] ?? null,
                    'palette' => $avatar['palette'] ?? null,
                    'pixels' => $avatar['pixels'] ?? null,
                    'imageUrl' => $avatar['image_url'] ?? null,
                ],
            ],
            // Android (Chrome): opens the profile in the app when it is installed, else this page.
            // The app's own page of the member (/membre/pseudo, no page of the site): the app opens
            // it on every Android version (/pseudo only from Android 12, vixeo_frontend's manifest).
            'openInApp' => str_contains((string) $request->userAgent(), 'Android')
                ? sprintf(
                    'intent://%s/membre/%s#Intent;scheme=https;package=%s;S.browser_fallback_url=%s;end',
                    parse_url($address, PHP_URL_HOST),
                    $profile->username,
                    config('vixeo.android.package'),
                    rawurlencode(self::siteUrl().'/#installer'),
                )
                : null,
        ])->toResponse($request)->header('X-Robots-Tag', 'noindex');
    }

    /** Android App Links: www.vixeo.app/pseudo opens in the app signed with our release key. */
    public function assetLinks(): JsonResponse
    {
        $fingerprints = array_values(array_filter(array_map(
            fn (string $value) => strtoupper(trim($value)),
            explode(',', (string) config('vixeo.android.cert_sha256')),
        )));

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('vixeo.android.package'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }

    /** Address of a profile's public page, shared from the app. */
    public static function memberUrl(string $username): string
    {
        return self::siteUrl().'/'.$username;
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

    /** Public address of the site (APP_URL), without trailing slash. */
    public static function siteUrl(): string
    {
        return rtrim((string) config('vixeo.urls.site'), '/');
    }
}
