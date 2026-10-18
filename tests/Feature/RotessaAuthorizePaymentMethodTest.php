<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Coverage for the Rotessa ACSS "add payment method" authorize flow:
 *  - phone numbers entered in common formats are normalized before validation/submission
 *  - Rotessa API validation errors are surfaced back to the form with the input preserved
 */
class RotessaAuthorizePaymentMethodTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $cg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ThrottleRequests::class, VerifyCsrfToken::class]);

        Model::reguard();

        $this->makeTestData();

        CompanyGateway::query()->withTrashed()->cursor()->each(fn ($cg) => $cg->forceDelete());

        $fees_and_limits = [];
        $fees_and_limits[GatewayType::ACSS] = ['min_limit' => -1,'max_limit' => -1,'fee_amount' => 0,'fee_percent' => 0,'fee_tax_name1' => '','fee_tax_rate1' => 0,'fee_tax_name2' => '','fee_tax_rate2' => 0,'fee_tax_name3' => '','fee_tax_rate3' => 0,'adjust_fee_percent' => false,'fee_cap' => 0,'is_enabled' => true];

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = '91be24c7b792230bced33e930ac61676';
        $cg->require_cvv = false;
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->update_details = false;
        $cg->config = encrypt(json_encode(['apiKey' => 'rotessa-test-key','testMode' => true]));
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();
        $this->cg = $cg;

        $settings = $this->client->settings;
        $settings->currency_id = '9'; // CAD
        $settings->company_gateway_ids = (string) $this->encodePrimaryKey($cg->id);
        $this->client->settings = $settings;
        $this->client->country_id = 124; // Canada
        $this->client->save();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'gateway_type_id' => GatewayType::ACSS,
            'company_gateway_id' => $this->cg->id,
            'country' => 'CA',
            'name' => 'John Doe',
            'address_1' => '123 Main St',
            'city' => 'Toronto',
            'email' => 'john@gmail.com',
            'province_code' => 'NB',
            'postal_code' => 'A1A1A1',
            'authorization_type' => 'Online',
            'account_number' => '123456789',
            'bank_name' => 'RBC',
            'phone' => '(867) 202-1634',
            'home_phone' => '(867) 202-1634',
            'institution_number' => '003',
            'transit_number' => '12345',
            'custom_identifier' => 'CUST-1',
            'customer_type' => 'Personal',
            'id' => '',
        ], $overrides);
    }

    private function actAsContact(): void
    {
        $this->actingAs(ClientContact::find($this->contact->id), 'contact');
    }

    public function testFormattedPhoneIsNormalizedAndCustomerCreated(): void
    {
        Http::fake([
            '*rotessa.com/*' => Http::response([
                'id' => 987,
                'account_number' => '123456789',
                'custom_identifier' => 'CUST-1',
                'transit_number' => '12345',
                'institution_number' => '003',
            ], 200),
        ]);

        $this->actAsContact();

        $response = $this->from(route('client.payment_methods.create', ['method' => GatewayType::ACSS]))
            ->post(route('client.payment_methods.store', ['method' => GatewayType::ACSS]), $this->payload([
                'home_phone' => '+1 (867) 202-1634',
                'phone' => '867.202.1634',
            ]));

        $response->assertRedirect(route('client.payment_methods.index'));
        $response->assertSessionHasNoErrors();

        // The bracketed/plus-prefixed phone is stripped to the bare 10 digit national number.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'customers')
                && $request['home_phone'] === '8672021634'
                && $request['phone'] === '8672021634';
        });

        $this->assertTrue(
            ClientGatewayToken::query()
                ->where('company_gateway_id', $this->cg->id)
                ->where('client_id', $this->client->id)
                ->where('gateway_customer_reference', 987)
                ->exists()
        );
    }

    public function testRotessaApiErrorIsShownAndInputPreserved(): void
    {
        Http::fake([
            '*rotessa.com/*' => Http::response([
                'errors' => [
                    ['error_code' => 'unknown_error', 'error_message' => 'Name is invalid'],
                ],
            ], 422),
        ]);

        $this->actAsContact();

        $response = $this->from(route('client.payment_methods.create', ['method' => GatewayType::ACSS]))
            ->post(route('client.payment_methods.store', ['method' => GatewayType::ACSS]), $this->payload());

        // Back to the form, not the index, so the client can correct and retry.
        $response->assertRedirect(route('client.payment_methods.create', ['method' => GatewayType::ACSS]));

        $bag = $response->getSession()->get('errors');
        $this->assertNotNull($bag);
        $this->assertContains('Name is invalid', $bag->all());

        // Previous input is preserved for re-population via old().
        $this->assertEquals('John Doe', $response->getSession()->getOldInput('name'));
        $this->assertEquals('8672021634', $response->getSession()->getOldInput('home_phone'));

        $this->assertFalse(
            ClientGatewayToken::query()
                ->where('company_gateway_id', $this->cg->id)
                ->where('client_id', $this->client->id)
                ->exists()
        );
    }

    public function testFrenchProvinceNamePreselectsCorrectCodeOnForm(): void
    {
        $this->client->state = 'Nouveau-Brunswick'; // French province name stored on the client
        $this->client->save();

        $this->actAsContact();

        $response = $this->get(route('client.payment_methods.create', ['method' => GatewayType::ACSS]));

        $response->assertOk();
        $response->assertSee(ctrans('texts.select_option')); // empty placeholder option present
        $response->assertSee('value="NB" selected', false); // pre-selected from the French name
    }

    public function testShortPhoneNumberFailsValidation(): void
    {
        Http::fake(['*rotessa.com/*' => Http::response([], 200)]);

        $this->actAsContact();

        $response = $this->from(route('client.payment_methods.create', ['method' => GatewayType::ACSS]))
            ->post(route('client.payment_methods.store', ['method' => GatewayType::ACSS]), $this->payload([
                'home_phone' => '202-1634',
            ]));

        $response->assertSessionHasErrors('home_phone');
        Http::assertNothingSent();
    }
}
