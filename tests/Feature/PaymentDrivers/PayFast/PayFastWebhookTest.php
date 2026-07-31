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

namespace Tests\Feature\PaymentDrivers\PayFast;

use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\PayFast\PaymentCompletedWebhook;
use App\PaymentDrivers\PayFast\CreditCard;
use App\PaymentDrivers\PayFastPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\MockAccountData;
use Tests\TestCase;

class PayFastWebhookTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const GATEWAY_KEY = 'd6814fc83f45d2935e7777071e629ef9';

    private const PASSPHRASE = 'test-passphrase';

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (! Gateway::find(11)) {
            $gateway = new Gateway();
            $gateway->id = 11;
            $gateway->name = 'PayFast';
            $gateway->key = self::GATEWAY_KEY;
            $gateway->provider = 'PayFast';
            $gateway->is_offsite = true;
            $gateway->fields = json_encode(['merchantId' => '', 'merchantKey' => '', 'pdtKey' => '', 'passphrase' => '', 'testMode' => false]);
            $gateway->visible = 1;
            $gateway->default_gateway_type_id = GatewayType::CREDIT_CARD;
            $gateway->save();
        }

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = self::GATEWAY_KEY;
        $this->company_gateway->config = encrypt(json_encode([
            'merchantId' => '10000100',
            'merchantKey' => '46f0cd694581a',
            'passphrase' => self::PASSPHRASE,
            'testMode' => true,
        ]));
        $this->company_gateway->fees_and_limits = '';
        $this->company_gateway->save();
    }

    /**
     * Build a fixture ITN body with several empty fields (the regression
     * trigger: ConvertEmptyStringsToNull + http_build_query was dropping these).
     *
     * @return array<string, string>
     */
    private function itnFields(string $m_payment_id, string $pf_payment_id = '1410364'): array
    {
        return [
            'm_payment_id' => $m_payment_id,
            'pf_payment_id' => $pf_payment_id,
            'payment_status' => 'COMPLETE',
            'item_name' => 'purchase',
            'item_description' => 'Invoices: ["0001"]',
            'amount_gross' => '100.00',
            'amount_fee' => '-2.30',
            'amount_net' => '97.70',
            'custom_str1' => '',
            'custom_str2' => '',
            'custom_str3' => '',
            'custom_str4' => '',
            'custom_str5' => '',
            'custom_int1' => '',
            'custom_int2' => '',
            'custom_int3' => '',
            'custom_int4' => '',
            'custom_int5' => '',
            'name_first' => '',
            'name_last' => '',
            'email_address' => '',
            'merchant_id' => '10000100',
        ];
    }

    private function sign(array $fields, string $passphrase = self::PASSPHRASE): string
    {
        $query = http_build_query($fields);

        if ($passphrase !== '') {
            $query .= '&passphrase=' . urlencode($passphrase);
        }

        return md5($query);
    }

    private function buildRawBody(array $fields, string $signature): string
    {
        $fields['signature'] = $signature;

        return http_build_query($fields);
    }

    private function makePaymentHash(string $hash, float $amount = 100.00): PaymentHash
    {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'amount_with_fee' => $amount,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => $amount]],
        ];

        $payment_hash = new PaymentHash();
        $payment_hash->hash = $hash;
        $payment_hash->fee_invoice_id = $invoice->id;
        $payment_hash->fee_total = 0;
        $payment_hash->data = $data;
        $payment_hash->save();

        return $payment_hash;
    }

    private function webhookUrl(): string
    {
        return route('payment_notification_webhook', [
            'company_key' => $this->company->company_key,
            'company_gateway_id' => $this->encodePrimaryKey($this->company_gateway->id),
            'client' => $this->encodePrimaryKey($this->client->id),
        ]);
    }

    public function testValidSignatureWithEmptyFieldsIsAccepted(): void
    {
        Queue::fake();

        $hash = str_repeat('a', 32);
        $this->makePaymentHash($hash);

        $fields = $this->itnFields($hash);
        $body = $this->buildRawBody($fields, $this->sign($fields));

        $response = $this->call(
            'POST',
            $this->webhookUrl(),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $body,
        );

        $response->assertStatus(200);
        Queue::assertPushed(PaymentCompletedWebhook::class);
    }

    public function testTamperedSignatureIsRejected(): void
    {
        Queue::fake();

        $hash = str_repeat('b', 32);
        $this->makePaymentHash($hash);

        $fields = $this->itnFields($hash);
        $body = $this->buildRawBody($fields, str_repeat('0', 32));

        $response = $this->call(
            'POST',
            $this->webhookUrl(),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $body,
        );

        $response->assertStatus(400);
        Queue::assertNotPushed(PaymentCompletedWebhook::class);
    }

    public function testJobCreatesPaymentAndIsIdempotent(): void
    {
        $hash = str_repeat('c', 32);
        $payment_hash = $this->makePaymentHash($hash);

        $fields = $this->itnFields($hash, '2000001');

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $payment = Payment::where('transaction_reference', '2000001')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(100.00, (float) $payment->amount);
        $this->assertEquals('2000001' . $payment_hash->hash, $payment->idempotency_key);

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertEquals(1, Payment::where('transaction_reference', '2000001')->count());
    }

    public function testAmountMismatchSkipsPaymentCreation(): void
    {
        $hash = str_repeat('d', 32);
        $this->makePaymentHash($hash, 100.00);

        $fields = $this->itnFields($hash, '2000002');
        $fields['amount_gross'] = '50.00';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertNull(Payment::where('transaction_reference', '2000002')->first());
    }

    public function testTokenStoredOnlyWhenStoreCardOptioned(): void
    {
        $hash_no_store = str_repeat('e', 32);
        $this->makePaymentHash($hash_no_store);

        $fields = $this->itnFields($hash_no_store, '2000003');
        $fields['token'] = 'pf-token-aaaa';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertEquals(0, ClientGatewayToken::where('client_id', $this->client->id)->where('token', 'pf-token-aaaa')->count());

        $hash_store = str_repeat('f', 32);
        $this->makePaymentHash($hash_store);

        $fields = $this->itnFields($hash_store, '2000004');
        $fields['custom_int1'] = '1';
        $fields['token'] = 'pf-token-bbbb';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertEquals(1, ClientGatewayToken::where('client_id', $this->client->id)->where('token', 'pf-token-bbbb')->count());

        $duplicate_hash = str_repeat('5', 32);
        $this->makePaymentHash($duplicate_hash);
        $fields = $this->itnFields($duplicate_hash, '2000008');
        $fields['custom_int1'] = '1';
        $fields['token'] = 'pf-token-bbbb';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertSame(1, ClientGatewayToken::where('client_id', $this->client->id)->where('token', 'pf-token-bbbb')->count());
    }

    public function testUnexpectedStoreCardMarkerDoesNotStoreToken(): void
    {
        $hash = str_repeat('0', 32);
        $this->makePaymentHash($hash);

        $fields = $this->itnFields($hash, '2000006');
        $fields['custom_int1'] = 'true';
        $fields['token'] = 'pf-token-cccc';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertFalse(ClientGatewayToken::where('token', 'pf-token-cccc')->exists());
    }

    public function testAuthorizationItnIsHandledBeforePaymentWebhookDispatch(): void
    {
        Queue::fake();

        $hash = str_repeat('2', 32);
        Cache::put($hash, 'cc_auth', 300);

        $fields = $this->itnFields($hash, '2000007');
        $fields['amount_gross'] = '5.00';
        $fields['custom_int1'] = '1';
        $fields['token'] = 'pf-auth-token';
        $body = $this->buildRawBody($fields, $this->sign($fields));

        $response = $this->call(
            'POST',
            $this->webhookUrl(),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $body,
        );

        $response->assertOk();
        Queue::assertNotPushed(PaymentCompletedWebhook::class);
        $this->assertSame(1, ClientGatewayToken::where('token', 'pf-auth-token')->count());

        $retry = $this->call(
            'POST',
            $this->webhookUrl(),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            $body,
        );

        $retry->assertOk();
        $this->assertSame(1, ClientGatewayToken::where('token', 'pf-auth-token')->count());
    }

    public function testCheckoutSignaturesIncludePassphraseWithoutSubmittingIt(): void
    {
        $driver = $this->company_gateway->driver($this->client)->init();
        $data = [
            'merchant_id' => '10000100',
            'merchant_key' => '46f0cd694581a',
            'amount' => '100.00',
            'item_name' => 'purchase',
        ];

        $standard = $driver->generateSignature($data, self::PASSPHRASE);
        $tokenized = $driver->generateSignature(array_merge($data, [
            'custom_int1' => 1,
            'payment_method' => 'cc',
            'subscription_type' => 2,
        ]), self::PASSPHRASE);

        $this->assertSame(md5(http_build_query($data) . '&passphrase=' . self::PASSPHRASE), $standard);
        $this->assertNotSame($standard, $tokenized);
        $this->assertArrayNotHasKey('passphrase', $data);
    }

    public function testTokenBillingSettingSelectsExpectedInitialPayload(): void
    {
        $payment_hash = $this->makePaymentHash(str_repeat('3', 32));

        foreach ([
            'off' => false,
            'always' => true,
            'optin' => false,
            'optout' => true,
        ] as $setting => $expected) {
            $this->company_gateway->token_billing = $setting;

            $driver = $this->company_gateway->driver($this->client)->init();
            $driver->setPaymentHash($payment_hash);
            $driver->setPaymentMethod(GatewayType::CREDIT_CARD);

            $data = $driver->payment_method->paymentData(array_merge(
                (array) $payment_hash->data,
                ['payment_hash' => $payment_hash->hash],
            ));

            $this->assertSame($expected, $data['tokenize']);
            $this->assertSame(
                $expected ? $data['tokenized_signature'] : $data['standard_signature'],
                $data['signature'],
            );
            $this->assertArrayNotHasKey('passphrase', $data);
        }
    }

    public function testStoredTokenPaymentDoesNotRequireItnAmount(): void
    {
        $payment_hash = $this->makePaymentHash(str_repeat('4', 32));
        $driver = $this->company_gateway->driver($this->client)->init();
        $driver->setPaymentHash($payment_hash);
        $driver->setPaymentMethod(GatewayType::CREDIT_CARD);
        $driver->payment_method->authorizeResponse(Request::create('/', 'POST', [
            'token' => 'pf-existing-token',
        ]));

        $payfast = $this->createMock(PayFastPaymentDriver::class);
        $payfast->client = $this->client;
        $payfast->payment_hash = $payment_hash;
        $payfast->expects($this->once())
            ->method('tokenBilling')
            ->willReturn((object) ['hashed_id' => 'payment-id']);

        $response = (new CreditCard($payfast))->paymentResponse(Request::create('/', 'POST', [
            'token' => 'pf-existing-token',
            'payment_hash' => $payment_hash->hash,
        ]));

        $this->assertTrue($response->isRedirect());
        $this->assertTrue(PaymentHash::find($payment_hash->id)->data->payfast_token_payment);

        $fields = $this->itnFields($payment_hash->hash, '2000009');
        $fields['custom_int1'] = '1';
        $fields['token'] = 'pf-unexpected-new-token';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertFalse(ClientGatewayToken::where('token', 'pf-unexpected-new-token')->exists());
    }

    public function testStandaloneAuthorizationDoesNotChargeTheCard(): void
    {
        $driver = $this->company_gateway->driver($this->client)->init();
        $driver->setPaymentMethod(GatewayType::CREDIT_CARD);

        $view = $driver->authorizeView([]);
        $data = $view->getData();

        $this->assertSame(0, $data['amount']);
        $this->assertSame(2, $data['subscription_type']);
    }

    public function testFailedStatusLogsFailureAndCreatesNoPayment(): void
    {
        Bus::fake([SystemLogger::class]);

        $hash = str_repeat('1', 32);
        $this->makePaymentHash($hash);

        $fields = $this->itnFields($hash, '2000005');
        $fields['payment_status'] = 'FAILED';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertNull(Payment::where('transaction_reference', '2000005')->first());
        Bus::assertDispatched(SystemLogger::class);
    }
}
