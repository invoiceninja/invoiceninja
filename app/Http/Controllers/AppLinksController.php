<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Utils\AppLinkPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared record links for the apps — invoiceninja/flutter#144.
 *
 * https://<instance>/app/invoices/<hashed id>?company=<hashed id>. Where the
 * app's manifest claims the host — only the hosted one, since Android and Apple
 * need build-time literals — the OS opens the app; everywhere else the browser
 * loads {@see self::bridge()}.
 */
class AppLinksController extends Controller
{
    /**
     * Routed rather than a file in public/ for the same reason as the Apple
     * document below. Unset fingerprints leave valid JSON that verifies nothing.
     */
    public function assetLinks(): JsonResponse
    {
        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('ninja.app_links.android_package'),
                    'sha256_cert_fingerprints' => $this->configList('ninja.app_links.android_fingerprints'),
                ],
            ],
        ]);
    }

    /**
     * Must be served as application/json, which an extension-less file is not,
     * and Apple does not follow redirects to it. Scoped to /app/* deliberately:
     * a wildcard would make the admin app swallow every client-portal and
     * payment link.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'details' => [
                    [
                        'appIDs' => $this->configList('ninja.app_links.apple_app_ids'),
                        'components' => [
                            [
                                '/' => '/app/*',
                                'comment' => 'Shared record links',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Where a shared link lands when the OS did not claim it — every
     * self-hosted domain — so the only thing that can offer those users the app.
     *
     * $path is attacker-supplied and ends up in hrefs: route-constrained,
     * collapsed here, escaped by Blade.
     */
    public function bridge(Request $request, string $path = '')
    {
        $path = trim(preg_replace('#/+#', '/', $path), '/');

        $query = $request->query();
        // Lets the app tell a link from another install apart: a custom scheme
        // URL carries no origin, and company hashids are per-instance, so a link
        // from one server can resolve to a different company's record on
        // another. Compared only, never navigated to.
        $query['server'] = $request->getSchemeAndHttpHost();

        // `native_scheme` is a URL prefix (`invoiceninja://app`), not a bare
        // scheme, and an intent URL needs the halves apart.
        $native = parse_url((string) config('ninja.app_links.native_scheme'));
        $scheme = $native['scheme'] ?? 'invoiceninja';
        $host = $native['host'] ?? 'app';

        $target = $host.'/'.$path.'?'.http_build_query($query);
        $webUrl = $this->webUrl($path, $request->query());

        // Android gets intent:// rather than the scheme: Chrome answers an
        // unregistered scheme with ERR_UNKNOWN_URL_SCHEME, an error page that
        // replaces the document and takes the fallback with it. An intent URL
        // carries its own browser_fallback_url.
        return response()
            ->view('app_links.bridge', [
                'appUrl' => $scheme.'://'.$target,
                'intentUrl' => 'intent://'.$target
                    .'#Intent;scheme='.$scheme
                    .';package='.config('ninja.app_links.android_package')
                    .';S.browser_fallback_url='.rawurlencode($webUrl)
                    .';end',
                'webUrl' => $webUrl,
            ])
            ->header('X-Robots-Tag', 'noindex');
    }

    private function webUrl(string $path, array $query): string
    {
        $base = rtrim((string) config('ninja.react_url') ?: url('/'), '/');
        $suffix = empty($query) ? '' : '?'.http_build_query($query);

        return $base.'/#/'.AppLinkPath::forWebClient($path).$suffix;
    }

    private function configList(string $key): array
    {
        return collect(explode(',', (string) config($key)))
            ->map(fn ($value) => trim($value))
            ->filter()
            ->values()
            ->all();
    }
}
