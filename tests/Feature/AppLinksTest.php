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

namespace Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Shared record links — invoiceninja/flutter#144. Everything here fails
 * silently in production: Android and Apple simply decline to verify, and the
 * links go on opening in a browser as though the feature had never shipped.
 *
 * @see \App\Http\Controllers\AppLinksController
 */
class AppLinksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testAssetLinksIsJsonAndNamesTheApp()
    {
        $response = $this->get('.well-known/assetlinks.json');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $statement = $response->json()[0];

        $this->assertSame(['delegate_permission/common.handle_all_urls'], $statement['relation']);
        $this->assertSame('android_app', $statement['target']['namespace']);
        $this->assertSame(
            config('ninja.app_links.android_package'),
            $statement['target']['package_name']
        );
        $this->assertIsArray($statement['target']['sha256_cert_fingerprints']);
    }

    public function testAppleAssociationIsJsonAndScopedToSharedLinks()
    {
        $response = $this->get('.well-known/apple-app-site-association');

        $response->assertStatus(200);
        // Apple rejects anything but JSON, and an extension-less file would
        // otherwise be served as application/octet-stream.
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $detail = $response->json()['applinks']['details'][0];

        $this->assertContains('NPC44Y2C98.com.invoiceninja.admin', $detail['appIDs']);
        // A wildcard here would make the admin app swallow every client-portal
        // and payment link on iOS.
        $this->assertSame('/app/*', $detail['components'][0]['/']);
    }

    public function testBridgeOffersTheAppAndTheWebClient()
    {
        $response = $this->get('app/invoices/Opnel5aKBz?company=VolejRejNm');

        $response->assertStatus(200);
        $response->assertSee('invoiceninja://app/invoices/Opnel5aKBz?company=VolejRejNm', false);
        // The web client needs the /edit suffix; a bare :id renders an empty page.
        $response->assertSee('/#/invoices/Opnel5aKBz/edit?company=VolejRejNm', false);
    }

    public function testBridgeTellsTheAppWhichInstanceSentTheLink()
    {
        // A custom-scheme URL carries no origin, and two self-hosted servers with
        // default hash salts number their companies identically.
        $this->get('app/clients/Wpmbk5ezJn?company=VolejRejNm')
            ->assertSee('server=', false);
    }

    public function testBridgeDoesNotSuffixAListRoute()
    {
        // Two segments like a record, but this is a list — which is why roots are
        // matched explicitly rather than counted.
        $this->get('app/settings/bank_accounts?company=VolejRejNm')
            ->assertSee('/#/settings/bank_accounts?company=VolejRejNm', false);
    }

    public function testBridgeOffersAndroidAnIntentUrlWithItsOwnFallback()
    {
        // Chrome answers an unregistered scheme with ERR_UNKNOWN_URL_SCHEME, an
        // error page that replaces the bridge and strands the one visitor this
        // page exists for. The intent URL carries a fallback instead.
        $response = $this->get('app/invoices/Opnel5aKBz?company=VolejRejNm');

        $response->assertSee('intent://app/invoices/Opnel5aKBz', false);
        $response->assertSee('scheme=invoiceninja', false);
        $response->assertSee('package=com.invoiceninja.admin', false);
        $response->assertSee('S.browser_fallback_url=', false);
        // The configured scheme is a whole URL prefix; pasting it in whole emits
        // intent://invoiceninja://app/... , which Chrome ignores.
        $response->assertDontSee('intent://invoiceninja', false);
    }

    public function testBridgeDoesNotDoubleAnEditSuffix()
    {
        // The app emits /edit itself for an entity with no detail screen.
        $response = $this->get('app/settings/bank_accounts/transaction_rules/Wpmbk5ezJn/edit');

        $response->assertSee('/#/settings/bank_accounts/transaction_rules/Wpmbk5ezJn/edit', false);
        $response->assertDontSee('/edit/edit', false);
    }

    public function testBridgeTranslatesARenamedScreen()
    {
        // The apps call these payment links; the web client, subscriptions.
        $this->get('app/settings/payment_links/Wpmbk5ezJn')
            ->assertSee('/#/settings/subscriptions/Wpmbk5ezJn/edit', false);
    }

    public function testBridgeRejectsAPathTheRouteConstraintForbids()
    {
        $this->get('app/../../etc/passwd')->assertStatus(404);
    }
}
