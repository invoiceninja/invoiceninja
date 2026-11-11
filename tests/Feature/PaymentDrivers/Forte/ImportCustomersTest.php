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

namespace Tests\Feature\PaymentDrivers\Forte;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\PaymentDrivers\FortePaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportCustomersTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const GATEWAY_KEY = 'kivcvjexxvdiyqtj3mju5d6yhpeht2xs';

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (! Gateway::find(59)) {
            $gateway = new Gateway();
            $gateway->id = 59;
            $gateway->name = 'Forte';
            $gateway->key = self::GATEWAY_KEY;
            $gateway->provider = 'Forte';
            $gateway->is_offsite = true;
            $gateway->fields = json_encode([
                'testMode' => true,
                'apiAccessId' => '',
                'secureKey' => '',
                'authOrganizationId' => '',
                'organizationId' => '',
                'locationId' => '',
            ]);
            $gateway->visible = 1;
            $gateway->default_gateway_type_id = GatewayType::CREDIT_CARD;
            $gateway->save();
        }

        $config = new \stdClass();
        $config->testMode = true;
        $config->apiLoginId = 'test_login';
        $config->apiAccessId = 'test_access';
        $config->secureKey = 'test_secret';
        $config->authOrganizationId = 'test_auth_org';
        $config->organizationId = 'org_test';
        $config->locationId = 'loc_test';

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = self::GATEWAY_KEY;
        $this->company_gateway->config = encrypt(json_encode($config));
        $this->company_gateway->fees_and_limits = '';
        $this->company_gateway->save();
    }

    private function driver(): FortePaymentDriver
    {
        /** @var FortePaymentDriver $driver */
        $driver = $this->company_gateway->driver();

        return $driver;
    }

    /**
     * @param  array<int, array<string, mixed>>  $customers
     */
    private function fakeForte(array $customers): void
    {
        Http::fake([
            'sandbox.forte.net/*' => Http::response(['results' => $customers], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function customer(string $token, string $email, array $paymethods = []): array
    {
        return [
            'customer_token' => $token,
            'first_name' => 'Test',
            'last_name' => 'User',
            'company_name' => 'Acme Co',
            'addresses' => [
                [
                    'address_token' => 'adr_1',
                    'email' => $email,
                    'phone' => '555-0100',
                    'physical_address' => [
                        'street_line1' => '1 Main St',
                        'street_line2' => 'Suite 1',
                        'locality' => 'Townsville',
                        'region' => 'TX',
                        'postal_code' => '75001',
                    ],
                ],
            ],
            'paymethods' => $paymethods,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPaymethod(string $token = 'mpt_card_1'): array
    {
        return [
            'paymethod_token' => $token,
            'card' => [
                'name_on_card' => 'Test User',
                'last_4_account_number' => '4242',
                'expire_month' => 12,
                'expire_year' => 2030,
                'card_type' => 'visa',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function echeckPaymethod(string $token = 'mpt_ach_1'): array
    {
        return [
            'paymethod_token' => $token,
            'echeck' => [
                'account_holder' => 'Test User',
                'last_4_account_number' => '6789',
                'account_type' => 'checking',
            ],
        ];
    }

    public function testNewClientCreatedAndPaymethodsImported(): void
    {
        $email = 'forte-new@example.test';

        $this->fakeForte([
            $this->customer('cst_new_1', $email, [
                $this->cardPaymethod('mpt_card_new'),
                $this->echeckPaymethod('mpt_ach_new'),
            ]),
        ]);

        $this->driver()->importCustomers();

        $contact = ClientContact::where('company_id', $this->company->id)->where('email', $email)->first();
        $this->assertNotNull($contact, 'Contact should have been created from Forte payload');

        $client = $contact->client;
        $this->assertInstanceOf(Client::class, $client);

        $tokens = ClientGatewayToken::where('client_id', $client->id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->get();

        $this->assertCount(2, $tokens);

        $card = $tokens->firstWhere('token', 'mpt_card_new');
        $this->assertNotNull($card);
        $this->assertSame('cst_new_1', $card->gateway_customer_reference);
        $this->assertSame(GatewayType::CREDIT_CARD, $card->gateway_type_id);
        $this->assertSame('4242', $card->meta->last4);
        $this->assertSame('visa', $card->meta->brand);
        $this->assertSame('12', $card->meta->exp_month);
        $this->assertSame('2030', $card->meta->exp_year);

        $ach = $tokens->firstWhere('token', 'mpt_ach_new');
        $this->assertNotNull($ach);
        $this->assertSame('cst_new_1', $ach->gateway_customer_reference);
        $this->assertSame(GatewayType::BANK_TRANSFER, $ach->gateway_type_id);
        $this->assertSame('6789', $ach->meta->last4);
        $this->assertSame('ACH', $ach->meta->brand);
    }

    public function testExistingClientReusedWhenContactEmailMatches(): void
    {
        $email = 'existing-forte@example.test';

        $existing_contact = $this->client->contacts()->first();
        $existing_contact->email = $email;
        $existing_contact->save();

        $clients_before = Client::where('company_id', $this->company->id)->count();

        $this->fakeForte([
            $this->customer('cst_existing_1', $email, [$this->cardPaymethod('mpt_card_existing')]),
        ]);

        $this->driver()->importCustomers();

        $clients_after = Client::where('company_id', $this->company->id)->count();
        $this->assertSame($clients_before, $clients_after, 'No new client should be created when one already matches');

        $tokens = ClientGatewayToken::where('client_id', $this->client->id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->get();

        $this->assertCount(1, $tokens);
        $this->assertSame('mpt_card_existing', $tokens->first()->token);
        $this->assertSame('cst_existing_1', $tokens->first()->gateway_customer_reference);
    }

    public function testRunningTwiceIsIdempotent(): void
    {
        $email = 'idempotent-forte@example.test';

        $this->fakeForte([
            $this->customer('cst_idem_1', $email, [
                $this->cardPaymethod('mpt_card_idem'),
                $this->echeckPaymethod('mpt_ach_idem'),
            ]),
        ]);

        $this->driver()->importCustomers();
        $this->driver()->importCustomers();

        $contact = ClientContact::where('company_id', $this->company->id)->where('email', $email)->first();
        $this->assertNotNull($contact);

        $count = ClientGatewayToken::where('client_id', $contact->client_id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->count();

        $this->assertSame(2, $count, 'Re-running import should not duplicate tokens');
    }

    public function testCustomerWithoutPaymethodsCreatesClientAndZeroTokens(): void
    {
        $email = 'no-paymethods-forte@example.test';

        $this->fakeForte([
            $this->customer('cst_no_pm', $email, []),
        ]);

        $this->driver()->importCustomers();

        $contact = ClientContact::where('company_id', $this->company->id)->where('email', $email)->first();
        $this->assertNotNull($contact);

        $count = ClientGatewayToken::where('client_id', $contact->client_id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->count();

        $this->assertSame(0, $count);
    }

    public function testCustomerWithoutEmailIsSkipped(): void
    {
        $tokens_before = ClientGatewayToken::where('company_id', $this->company->id)->count();
        $clients_before = Client::where('company_id', $this->company->id)->count();

        $payload = [
            'customer_token' => 'cst_no_email',
            'first_name' => 'Anon',
            'last_name' => 'Anon',
            'company_name' => 'Anon Co',
            'addresses' => [],
            'paymethods' => [$this->cardPaymethod('mpt_card_skip')],
        ];

        $this->fakeForte([$payload]);

        $this->driver()->importCustomers();

        $this->assertSame($clients_before, Client::where('company_id', $this->company->id)->count());
        $this->assertSame($tokens_before, ClientGatewayToken::where('company_id', $this->company->id)->count());
    }

    public function testUnknownPaymethodShapeIsSkipped(): void
    {
        $email = 'unknown-pm-forte@example.test';

        $unknown = [
            'paymethod_token' => 'mpt_unknown',
            'apple_pay' => ['device_id' => 'abc'],
        ];

        $this->fakeForte([
            $this->customer('cst_unknown_pm', $email, [
                $unknown,
                $this->cardPaymethod('mpt_card_known'),
            ]),
        ]);

        $this->driver()->importCustomers();

        $contact = ClientContact::where('company_id', $this->company->id)->where('email', $email)->first();
        $this->assertNotNull($contact);

        $tokens = ClientGatewayToken::where('client_id', $contact->client_id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->get();

        $this->assertCount(1, $tokens);
        $this->assertSame('mpt_card_known', $tokens->first()->token);
    }
}
