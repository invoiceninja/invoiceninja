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
 * Regression: PaymentMethod::getMethods() previously collapsed every CREDIT_CARD
 * gateway to the first entry via intersectByKeys(flatten(1)->unique()), so
 * Authorize/Checkout never appeared in the portal Pay Now dropdown when Stripe
 * was also configured.
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

        $this->createGateway(self::STRIPE_KEY, config('ninja.testvars.stripe'));
        $this->createGateway(self::CHECKOUT_KEY, [
            'publicApiKey' => 'pk_test_checkout',
            'secretApiKey' => 'sk_test_checkout',
            'testMode' => true,
        ]);
        $this->createGateway(self::AUTHORIZE_KEY, [
            'apiLoginId' => 'login',
            'transactionKey' => 'trans',
            'testMode' => true,
            'developerMode' => true,
        ]);
    }

    public function testPayNowOffersEveryEnabledCreditCardGateway(): void
    {
        $methods = collect($this->client->fresh()->service()->getPaymentMethods(42.0));

        $this->assertTrue(
            $methods->contains(
                fn (array $method) => $method['gateway_key'] === self::STRIPE_KEY
                    && (int) $method['gateway_type_id'] === GatewayType::CREDIT_CARD
            )
        );
        $this->assertTrue(
            $methods->contains(
                fn (array $method) => $method['gateway_key'] === self::CHECKOUT_KEY
                    && (int) $method['gateway_type_id'] === GatewayType::CREDIT_CARD
            )
        );
        $this->assertTrue(
            $methods->contains(
                fn (array $method) => $method['gateway_key'] === self::AUTHORIZE_KEY
                    && (int) $method['gateway_type_id'] === GatewayType::CREDIT_CARD
            )
        );
    }

    /**
     * @param  array<string, mixed>|string  $config
     */
    private function createGateway(string $gatewayKey, array|string $config): void
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
    }
}
