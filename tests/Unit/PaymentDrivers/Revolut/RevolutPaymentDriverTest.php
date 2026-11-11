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

namespace Tests\Unit\PaymentDrivers\Revolut;

use App\Exceptions\PaymentFailed;
use App\Models\CompanyGateway;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\RevolutPaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Order recording, amounts, refunds, credentials and webhooks of the
 * Revolut driver. Revolut is never called: Http is faked.
 */
class RevolutPaymentDriverTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const SIGNING_SECRET = 'wsk_test_secret';

    private RevolutPaymentDriver $driver;

    private CompanyGateway $company_gateway;

    private PaymentHash $payment_hash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    public function testGatewayConstsMapRevolutToItsSystemLogType(): void
    {
        $this->assertSame(SystemLog::TYPE_REVOLUT, (new CompanyGateway())->gateway_consts['764562d0ba104d73081499b2a40df091'] ?? null);
    }

    public function testCompletedOrderCreatesCompletedPayment(): void
    {
        $this->buildDriver(100.00);

        $payment = $this->driver->recordOrder($this->order('completed', 10000));

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertSame('order_1', $payment->transaction_reference);
        $this->assertSame(PaymentType::HOSTED_PAGE, (int) $payment->type_id);
        $this->assertEquals(100.00, $payment->amount);
    }

    public function testAuthorisedOrderIsNotTreatedAsPaid(): void
    {
        $this->buildDriver(100.00);

        $payment = $this->driver->recordOrder($this->order('authorised', 10000));

        $this->assertSame(Payment::STATUS_PENDING, $payment->status_id);
    }

    public function testPendingPaymentIsCompletedByALaterCompletedOrder(): void
    {
        $this->buildDriver(100.00);

        $pending = $this->driver->recordOrder($this->order('processing', 10000));
        $completed = $this->driver->recordOrder($this->order('completed', 10000));

        $this->assertSame($pending->id, $completed->id);
        $this->assertSame(Payment::STATUS_COMPLETED, $pending->fresh()->status_id);
    }

    public function testPendingPaymentFailsWhenTheOrderIsCancelled(): void
    {
        $this->buildDriver(100.00);

        $pending = $this->driver->recordOrder($this->order('authorised', 10000));

        $this->assertNull($this->driver->recordOrder($this->order('cancelled', 10000)));
        $this->assertSame(Payment::STATUS_FAILED, $pending->fresh()->status_id);
    }

    public function testUnpaidOrderRecordsNothing(): void
    {
        $this->buildDriver(100.00);

        $this->assertNull($this->driver->recordOrder($this->order('pending', 10000)));
        $this->assertNull($this->driver->recordOrder($this->order('failed', 10000)));
        $this->assertSame(0, Payment::where('transaction_reference', 'order_1')->count());
    }

    public function testRecordingTheSameOrderTwiceCreatesOnePayment(): void
    {
        $this->buildDriver(100.00);

        $first = $this->driver->recordOrder($this->order('completed', 10000));
        $second = $this->driver->recordOrder($this->order('completed', 10000));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::where('transaction_reference', 'order_1')->count());
    }

    public function testOrderWithADifferentAmountIsRejected(): void
    {
        $this->buildDriver(100.00);

        $this->expectException(PaymentFailed::class);

        $this->driver->recordOrder($this->order('completed', 100));
    }

    public function testAmountsFollowTheClientCurrencyPrecision(): void
    {
        $this->buildDriver(100.00);

        $this->client->currency()->precision = 0;
        $this->assertSame(1235, $this->driver->convertToRevolutAmount(1234.5));

        $this->client->currency()->precision = 2;
        $this->assertSame(123450, $this->driver->convertToRevolutAmount(1234.5));
        $this->assertSame(1234.5, $this->driver->convertFromRevolutAmount(123450));
    }

    public function testRefundSendsMinorUnitsAndReportsFailures(): void
    {
        $this->buildDriver(100.00);
        $payment = $this->driver->recordOrder($this->order('completed', 10000));

        Http::fake(['*/api/orders/order_1/refund' => Http::response(['code' => 'refund_not_allowed', 'message' => 'Order cannot be refunded'], 400)]);

        $result = $this->driver->refund($payment, 25.10);

        $this->assertFalse($result['success']);
        $this->assertSame('Order cannot be refunded', $result['description']);
        Http::assertSent(fn (Request $request) => $request['amount'] === 2510
            && $request->hasHeader('Revolut-Api-Version', \App\PaymentDrivers\Revolut\MerchantApi::API_VERSION)
            && str_starts_with($request->url(), \App\PaymentDrivers\Revolut\MerchantApi::SANDBOX_URL));
    }

    public function testAuthChecksTheApiKey(): void
    {
        $this->buildDriver(100.00);

        Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->assertSame('error', $this->driver->auth());
    }

    public function testWebhookSignatureVerification(): void
    {
        $this->buildDriver(100.00);

        $body = '{"event":"ORDER_COMPLETED","order_id":"order_1"}';
        $timestamp = (string) (int) (microtime(true) * 1000);
        $signature = 'v1=' . hash_hmac('sha256', "v1.{$timestamp}.{$body}", self::SIGNING_SECRET);

        $this->assertTrue($this->driver->verifyWebhookSignature($body, $timestamp, $signature, self::SIGNING_SECRET));
        $this->assertTrue($this->driver->verifyWebhookSignature($body, $timestamp, 'v1=deadbeef,' . $signature, self::SIGNING_SECRET));
        $this->assertFalse($this->driver->verifyWebhookSignature($body, $timestamp, $signature, 'other_secret'));
        $this->assertFalse($this->driver->verifyWebhookSignature($body . ' ', $timestamp, $signature, self::SIGNING_SECRET));

        $stale = (string) ((int) $timestamp - 10 * 60 * 1000);
        $stale_signature = 'v1=' . hash_hmac('sha256', "v1.{$stale}.{$body}", self::SIGNING_SECRET);
        $this->assertFalse($this->driver->verifyWebhookSignature($body, $stale, $stale_signature, self::SIGNING_SECRET));
    }

    public function testSignedWebhookRecordsTheOrderReadBackFromRevolut(): void
    {
        $this->buildDriver(100.00);

        Http::fake(['*/api/orders/order_1' => Http::response($this->order('completed', 10000))]);

        $body = json_encode(['event' => 'ORDER_COMPLETED', 'order_id' => 'order_1', 'merchant_order_ext_ref' => $this->payment_hash->hash]);

        $this->postSignedWebhook($body)->assertOk();
        $this->postSignedWebhook($body)->assertOk();

        $this->assertSame(1, Payment::where('transaction_reference', 'order_1')->where('status_id', Payment::STATUS_COMPLETED)->count());
    }

    public function testWebhookWithABadSignatureIsRejected(): void
    {
        $this->buildDriver(100.00);

        Http::fake();

        $body = json_encode(['event' => 'ORDER_COMPLETED', 'order_id' => 'order_1', 'merchant_order_ext_ref' => $this->payment_hash->hash]);

        $this->postSignedWebhook($body, 'wrong_secret')->assertStatus(401);

        Http::assertNothingSent();
        $this->assertSame(0, Payment::where('transaction_reference', 'order_1')->count());
    }

    public function testWebhookForAnotherOrderIsIgnored(): void
    {
        $this->buildDriver(100.00);

        Http::fake();

        $body = json_encode(['event' => 'ORDER_COMPLETED', 'order_id' => 'someone_elses_order', 'merchant_order_ext_ref' => $this->payment_hash->hash]);

        $this->postSignedWebhook($body)->assertOk();

        Http::assertNothingSent();
    }

    private function postSignedWebhook(string $body, string $secret = self::SIGNING_SECRET)
    {
        $timestamp = (string) (int) (microtime(true) * 1000);

        return $this->call('POST', route('payment_webhook', [
            'company_key' => $this->company->company_key,
            'company_gateway_id' => $this->company_gateway->hashed_id,
        ]), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_REVOLUT_REQUEST_TIMESTAMP' => $timestamp,
            'HTTP_REVOLUT_SIGNATURE' => 'v1=' . hash_hmac('sha256', "v1.{$timestamp}.{$body}", $secret),
        ], $body);
    }

    private function order(string $state, int $amount): array
    {
        return [
            'id' => 'order_1',
            'state' => $state,
            'amount' => $amount,
            'currency' => $this->client->getCurrencyCode(),
        ];
    }

    private function buildDriver(float $amount_with_fee): void
    {
        $gateway = Gateway::where('provider', 'Revolut')->firstOrFail();

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = $gateway->key;
        $this->company_gateway->setConfig(['apiKey' => 'sk_test', 'signingSecret' => self::SIGNING_SECRET, 'testMode' => true]);
        $this->company_gateway->save();

        $this->payment_hash = PaymentHash::create([
            'company_id' => $this->company->id,
            'hash' => str()->random(32),
            'fee_invoice_id' => $this->invoice->id,
            'data' => json_decode(json_encode([
                'amount_with_fee' => $amount_with_fee,
                'invoices' => [],
                'order_id' => 'order_1',
                'gateway_type_id' => GatewayType::HOSTED_PAGE,
            ])),
        ]);

        $this->driver = new RevolutPaymentDriver($this->company_gateway, $this->client, null);
        $this->driver->setPaymentHash($this->payment_hash);
        $this->driver->setPaymentMethod(GatewayType::HOSTED_PAGE);
    }
}
