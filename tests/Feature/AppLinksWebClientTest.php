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
     */
    private function webClientIsFlutter(bool $flutter): void
    {
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

    public function testHostedAlwaysGetsReact()
    {
        // Hosted serves the two from different hosts and never reads the flag.
        $this->webClientIsFlutter(true);
        config(['ninja.environment' => 'hosted']);

        $this->get('app/settings/payment_links/Wp1')
            ->assertSee('/#/settings/subscriptions/Wp1/edit', false);
    }
}
