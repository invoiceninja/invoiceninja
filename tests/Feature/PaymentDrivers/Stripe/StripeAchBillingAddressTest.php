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

namespace Tests\Feature\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Livewire\RequiredClientInfo;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Country;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Nacha requires a complete billing address on the us_bank_account payment method.
 *
 * Covers the client to Stripe address mapping and the backfill command that stamps
 * it onto payment methods created before the requirement landed.
 */
class StripeAchBillingAddressTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        Model::reguard();

        $this->makeTestData();

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = self::STRIPE_GATEWAY_KEY;
        $this->company_gateway->require_billing_address = false;
        $this->company_gateway->require_postal_code = false;
        $this->company_gateway->config = encrypt(json_encode(['apiKey' => 'sk_test_backfill', 'publishableKey' => 'pk_test_backfill']));
        $this->company_gateway->save();

        $this->client->address1 = '100 Main St';
        $this->client->address2 = 'Suite 4';
        $this->client->city = 'Austin';
        $this->client->state = 'TX';
        $this->client->postal_code = '78701';
        $this->client->country_id = 840;
        $this->client->save();
    }

    private function driver(): StripePaymentDriver
    {
        /** @var StripePaymentDriver $driver */
        $driver = $this->company_gateway->driver($this->client->fresh());

        return $driver;
    }

    private function achToken(): ClientGatewayToken
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $this->company_gateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = 'pm_backfill_test';
        $token->gateway_customer_reference = 'cus_backfill_test';
        $token->meta = (object) ['brand' => 'Test Bank (ACH)', 'last4' => '1234'];
        $token->save();

        return $token;
    }

    public function testBuildsTheStripeAddressFromTheClient(): void
    {
        $this->assertSame([
            'line1' => '100 Main St',
            'line2' => 'Suite 4',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'country' => 'US',
        ], $this->driver()->stripeBillingAddress());
    }

    public function testReturnsAnEmptyCountryWhenTheClientHasNone(): void
    {
        $this->client->country_id = null;
        $this->client->save();

        $this->assertSame('', $this->driver()->stripeBillingAddress()['country']);
    }

    public function testACompleteAddressDoesNotRequireLineTwo(): void
    {
        $this->client->address2 = '';
        $this->client->save();

        $this->assertTrue($this->driver()->hasCompleteBillingAddress());
    }

    public function testAMissingPostalCodeIsAnIncompleteAddress(): void
    {
        $this->client->postal_code = '';
        $this->client->save();

        $this->assertFalse($this->driver()->hasCompleteBillingAddress());
    }

    public function testAMissingCountryIsAnIncompleteAddress(): void
    {
        $this->client->country_id = null;
        $this->client->save();

        $this->assertFalse($this->driver()->hasCompleteBillingAddress());
    }

    public function testIncompleteAddressCannotBeSynchronizedToAnAchPaymentMethod(): void
    {
        $this->client->postal_code = '';
        $this->client->save();

        $this->expectException(PaymentFailed::class);
        $this->expectExceptionCode(400);

        $this->driver()->syncAchPaymentMethodBillingAddress($this->achToken());
    }

    public function testLegacyBankSourceDoesNotUseThePaymentMethodUpdateApi(): void
    {
        $this->expectNotToPerformAssertions();

        $token = $this->achToken();
        $token->token = 'ba_legacy_bank_source';

        $this->driver()->syncAchPaymentMethodBillingAddress($token);
    }

    public function testAchFieldsAreForcedRegardlessOfTheGatewayToggles(): void
    {
        $fields = collect($this->driver()->setPaymentMethod(GatewayType::BANK_TRANSFER)->getClientRequiredFields())
            ->pluck('name');

        $this->assertTrue($fields->contains('client_address_line_1'));
        $this->assertTrue($fields->contains('client_city'));
        $this->assertTrue($fields->contains('client_state'));
        $this->assertTrue($fields->contains('client_country_id'));
        $this->assertTrue($fields->contains('client_postal_code'));
    }

    public function testOtherMethodsStillHonourTheGatewayToggles(): void
    {
        $fields = collect($this->driver()->setPaymentMethod(GatewayType::CREDIT_CARD)->getClientRequiredFields())
            ->pluck('name');

        $this->assertFalse($fields->contains('client_address_line_1'));
        $this->assertFalse($fields->contains('client_postal_code'));
    }

    public function testRequiredFieldsDispatchTheFreshBillingAddress(): void
    {
        $this->client->address1 = '';
        $this->client->address2 = 'Suite 4';
        $this->client->city = '';
        $this->client->state = '';
        $this->client->postal_code = '';
        $this->client->country_id = null;
        $this->client->save();

        $fields = $this->driver()
            ->setPaymentMethod(GatewayType::BANK_TRANSFER)
            ->getClientRequiredFields();

        Livewire::test(RequiredClientInfo::class, [
            'db' => $this->company->db,
            'fields' => $fields,
            'contact_id' => $this->contact->id,
            'countries' => Country::all(),
            'company_id' => $this->company->id,
            'company_gateway_id' => $this->company_gateway->id,
        ])
            ->call('handleSubmit', [
                'contact_first_name' => 'Jane',
                'contact_last_name' => 'Doe',
                'contact_email' => 'jane@example.net',
                'client_address_line_1' => '200 Market St',
                'client_city' => 'San Francisco',
                'client_state' => 'CA',
                'client_country_id' => 840,
                'client_postal_code' => '94105',
            ])
            ->assertDispatched(
                'passed-required-fields-check',
                client_postal_code: '94105',
                billingAddress: [
                    'line1' => '200 Market St',
                    'line2' => 'Suite 4',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'postal_code' => '94105',
                    'country' => 'US',
                ],
            );
    }

    public function testTheDryRunLeavesTokensUntouched(): void
    {
        $token = $this->achToken();

        $this->artisan('ninja:stripe-ach-billing-address', [
            '--dry-run' => true,
            '--company_gateway_id' => $this->company_gateway->id,
        ])->assertExitCode(0);

        $this->assertFalse(isset($token->fresh()->meta->billing_address_synced));
    }

    public function testAnIncompleteAddressIsReportedRatherThanSent(): void
    {
        $this->achToken();

        $this->client->postal_code = '';
        $this->client->save();

        $this->artisan('ninja:stripe-ach-billing-address', [
            '--company_gateway_id' => $this->company_gateway->id,
        ])
            ->expectsOutputToContain('client address is incomplete')
            ->assertExitCode(0);
    }
}
