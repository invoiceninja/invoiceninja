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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
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

    private function makePaymentHash(string $hash, float $amount = 100.00, bool $store_card = false): PaymentHash
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

        if ($store_card) {
            $data['store_card'] = true;
        }

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
            [], [], [],
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
            [], [], [],
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
        $this->makePaymentHash($hash_no_store, 100.00, store_card: false);

        $fields = $this->itnFields($hash_no_store, '2000003');
        $fields['token'] = 'pf-token-aaaa';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertEquals(0, ClientGatewayToken::where('client_id', $this->client->id)->where('token', 'pf-token-aaaa')->count());

        $hash_store = str_repeat('f', 32);
        $this->makePaymentHash($hash_store, 100.00, store_card: true);

        $fields = $this->itnFields($hash_store, '2000004');
        $fields['token'] = 'pf-token-bbbb';

        (new PaymentCompletedWebhook($fields, $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertEquals(1, ClientGatewayToken::where('client_id', $this->client->id)->where('token', 'pf-token-bbbb')->count());
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
