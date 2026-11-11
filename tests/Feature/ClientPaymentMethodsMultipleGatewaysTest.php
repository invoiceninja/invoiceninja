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

use App\DataMapper\FeesAndLimits;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Regression coverage for companies with multiple credit card gateways.
 */
class ClientPaymentMethodsMultipleGatewaysTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    private const CHECKOUT_KEY = '3758e7f7c6f4cecf0f4f348b9a00f456';

    private const AUTHORIZE_KEY = '3b6621f970ab18887c4f6dca78d3f8bb';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->makeTestData();

        CompanyGateway::query()->withTrashed()->cursor()->each(
            fn (CompanyGateway $cg) => $cg->forceDelete()
        );

        $stripe = $this->createGateway(self::STRIPE_KEY, config('ninja.testvars.stripe'));
        $checkout = $this->createGateway(self::CHECKOUT_KEY, [
            'publicApiKey' => 'pk_test_checkout',
            'secretApiKey' => 'sk_test_checkout',
            'testMode' => true,
        ]);
        $authorize = $this->createGateway(self::AUTHORIZE_KEY, [
            'apiLoginId' => 'login',
            'transactionKey' => 'trans',
            'testMode' => true,
            'developerMode' => true,
        ]);

        $settings = $this->client->settings;
        $settings->company_gateway_ids = implode(',', [
            $stripe->hashed_id,
            $checkout->hashed_id,
            $authorize->hashed_id,
        ]);
        $this->client->settings = $settings;
        $this->client->save();
    }

    public function testPayNowOffersOnlyTheFirstEnabledCreditCardGateway(): void
    {
        $methods = collect($this->client->fresh()->service()->getPaymentMethods(42.0));
        $credit_card_methods = $methods
            ->where('gateway_type_id', GatewayType::CREDIT_CARD)
            ->values();

        $this->assertCount(1, $credit_card_methods);
        $this->assertSame(self::STRIPE_KEY, $credit_card_methods->first()['gateway_key']);
    }

    /**
     * @param  array<string, mixed>|string  $config
     */
    private function createGateway(string $gatewayKey, array|string $config): CompanyGateway
    {
        $fees = new FeesAndLimits();
        $fees->is_enabled = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = $gatewayKey;
        $cg->require_cvv = false;
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->update_details = false;
        $cg->config = encrypt(is_string($config) ? $config : json_encode($config));
        $cg->fees_and_limits = [
            GatewayType::CREDIT_CARD => $fees,
        ];
        $cg->save();

        return $cg;
    }
}
