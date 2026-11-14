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

use App\Models\Account;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Which web client a link continues to — invoiceninja/flutter#144.
 *
 * Split from {@see AppLinksTest}, which needs no database: these turn on
 * `accounts.set_react_as_default_ap`. Getting it wrong is silent — the link
 * still resolves, just into a client the recipient does not use.
 */
class AppLinksWebClientTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * `Account::first()` is whatever the database hands back, so set them all
     * rather than create one and hope. Rolled back with the transaction.
     *
     * Self-host too: only a self-hosted install reads the flag, and CI runs as
     * hosted.
     */
    private function webClientIsFlutter(bool $flutter): void
    {
        config(['ninja.environment' => 'selfhost']);

        if (Account::count() === 0) {
            Account::factory()->create();
        }

        Account::query()->update(['set_react_as_default_ap' => ! $flutter]);
    }

    public function testBridgeSendsAFlutterAccountToTheAppsOwnRoute()
    {
        // Untranslated: no `subscriptions` rename, no `/edit`.
        $this->webClientIsFlutter(true);

        $response = $this->get('app/settings/payment_links/Wp1?company=VolejRejNm');

        $response->assertStatus(200);
        $response->assertSee('/#/settings/payment_links/Wp1?company=VolejRejNm', false);
        $response->assertDontSee('/#/settings/subscriptions', false);
        // One $webUrl feeds the page link and Android's fallback alike.
        $response->assertSee(
            'S.browser_fallback_url='.rawurlencode(
                rtrim((string) config('ninja.app_url') ?: url('/'), '/')
                .'/#/settings/payment_links/Wp1?company=VolejRejNm'
            ),
            false
        );
    }

    public function testBridgeLeavesAReactAccountOnReact()
    {
        $this->webClientIsFlutter(false);

        $this->get('app/settings/payment_links/Wp1?company=VolejRejNm')
            ->assertSee('/#/settings/subscriptions/Wp1/edit?company=VolejRejNm', false);
    }

    public function testSelfHostReactIgnoresAStaleReactUrl()
    {
        // The server serves React at its own root, so REACT_URL is not read:
        // installs keep .env.example's dev-server value, and nothing listens on it.
        $this->webClientIsFlutter(false);
        config([
            'ninja.react_url' => 'http://localhost:3001',
            'ninja.app_url' => 'https://portal.example.com',
        ]);

        $webUrl = 'https://portal.example.com/#/invoices/Wp1/edit?company=VolejRejNm';

        $this->get('app/invoices/Wp1?company=VolejRejNm')
            ->assertSee($webUrl, false)
            ->assertSee('S.browser_fallback_url='.rawurlencode($webUrl), false)
            ->assertDontSee('3001', false);
    }

    public function testHostedAlwaysGetsReact()
    {
        // Hosted serves the two from different hosts and never reads the flag.
        $this->webClientIsFlutter(true);
        config([
            'ninja.environment' => 'hosted',
            'ninja.react_url' => 'https://react.example.com',
            'ninja.app_url' => 'https://api.example.com',
        ]);

        $this->get('app/settings/payment_links/Wp1')
            ->assertSee('https://react.example.com/#/settings/subscriptions/Wp1/edit', false);
    }

    public function testHostedSendsABrowserThatLastOpenedTheFlutterWebAppThere()
    {
        // React stays the default. The page switches only for a browser whose
        // invoicing.co landing page last opened the Flutter web app.
        $this->webClientIsFlutter(false);
        config([
            'ninja.environment' => 'hosted',
            'ninja.react_url' => 'https://react.example.com',
            'ninja.flutter_web_url' => 'https://admin.example.com',
        ]);

        $this->get('app/invoices/Wp1?company=VolejRejNm')
            ->assertSee('https://react.example.com/#/invoices/Wp1/edit?company=VolejRejNm', false)
            // Untranslated, as the apps route it; JSON-escaped in the script.
            ->assertSee('https:\/\/admin.example.com\/#\/invoices\/Wp1?company=VolejRejNm', false)
            ->assertSee("localStorage.getItem('invoiceninja.landing.last') === 'app'", false)
            // The continue-in-browser link reads it again, from its own attribute.
            ->assertSee('data-flutter="https://admin.example.com/#/invoices/Wp1?company=VolejRejNm"', false);
    }

    public function testHostedWithoutAFlutterWebUrlOnlyKnowsReact()
    {
        $this->webClientIsFlutter(false);
        config([
            'ninja.environment' => 'hosted',
            'ninja.react_url' => 'https://react.example.com',
            'ninja.flutter_web_url' => '',
        ]);

        $this->get('app/invoices/Wp1')
            ->assertSee('https://react.example.com/#/invoices/Wp1/edit', false)
            ->assertDontSee('invoiceninja.landing.last', false);
    }

    public function testHostedAndroidFallsBackToTheRememberedFlutterWebApp()
    {
        // An intent carries its own fallback, so the page offers one for each web app.
        $this->webClientIsFlutter(false);
        config([
            'ninja.environment' => 'hosted',
            'ninja.react_url' => 'https://react.example.com',
            'ninja.flutter_web_url' => 'https://admin.example.com',
        ]);

        $this->get('app/invoices/Wp1')
            ->assertSee('S.browser_fallback_url='.rawurlencode('https://react.example.com/#/invoices/Wp1/edit'), false)
            ->assertSee('S.browser_fallback_url='.rawurlencode('https://admin.example.com/#/invoices/Wp1'), false);
    }

    public function testSelfHostNeverOffersAFlutterWebUrl()
    {
        // Self-host serves its web client at its own root; FLUTTER_WEB_URL is hosted-only.
        $this->webClientIsFlutter(false);
        config(['ninja.flutter_web_url' => 'https://admin.example.com']);

        $this->get('app/invoices/Wp1')
            ->assertDontSee('admin.example.com', false)
            ->assertDontSee('invoiceninja.landing.last', false);
    }
}
