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

namespace Tests\Feature\PaymentDrivers\Razorpay;

use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\Razorpay\Hosted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\MockAccountData;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const GATEWAY_KEY = 'hxd6gwg3ekb9tb3v9lptgx1mqyg69zu9';

    private const API_SECRET = 'razorpay_api_secret';

    private const WEBHOOK_SECRET = 'razorpay_webhook_secret';

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->company_gateway = $this->makeGateway(self::WEBHOOK_SECRET);
    }

    public function test_captured_webhook_records_completed_payment(): void
    {
        $this->makePaymentHash('order_captured');
        $balance = (float) $this->invoice->fresh()->balance;

        $this->postWebhook('payment.captured', 'pay_captured', 'order_captured')->assertStatus(200);

        $payment = Payment::query()->where('transaction_reference', 'pay_captured')->first();

        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertSame($this->client->id, $payment->client_id);
        $this->assertEqualsWithDelta($balance - 1, (float) $this->invoice->fresh()->balance, 0.001);
    }

    public function test_order_paid_webhook_records_completed_payment(): void
    {
        $this->makePaymentHash('order_paid');

        $this->postWebhook('order.paid', 'pay_order_paid', 'order_paid')->assertStatus(200);

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_order_paid')->count());
    }

    public function test_repeated_webhooks_record_one_payment(): void
    {
        $this->makePaymentHash('order_repeat');

        $this->postWebhook('payment.captured', 'pay_repeat', 'order_repeat')->assertStatus(200);
        $this->postWebhook('order.paid', 'pay_repeat', 'order_repeat')->assertStatus(200);
        $this->postWebhook('payment.captured', 'pay_repeat', 'order_repeat')->assertStatus(200);

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_repeat')->count());
    }

    public function test_browser_return_after_webhook_does_not_record_a_second_payment(): void
    {
        $payment_hash = $this->makePaymentHash('order_return');

        $this->postWebhook('payment.captured', 'pay_return', 'order_return')->assertStatus(200);

        $driver = $this->company_gateway->driver($this->client);
        $driver->init();
        $driver->setPaymentHash($payment_hash);

        $request = PaymentResponseRequest::create('/client/payments/response', 'POST', [
            'payment_hash' => $payment_hash->hash,
            'company_gateway_id' => $this->company_gateway->hashed_id,
            'razorpay_payment_id' => 'pay_return',
            'razorpay_signature' => hash_hmac('sha256', 'order_return|pay_return', self::API_SECRET),
        ]);
        $request->setContainer($this->app);

        (new Hosted($driver))->paymentResponse($request);

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_return')->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->makePaymentHash('order_bad_signature');

        $this->postWebhook('payment.captured', 'pay_bad_signature', 'order_bad_signature', 'not_the_webhook_secret')
            ->assertStatus(400);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_bad_signature')->exists());
    }

    public function test_webhook_is_ignored_without_a_webhook_secret(): void
    {
        $this->company_gateway = $this->makeGateway('');
        $this->makePaymentHash('order_no_secret');

        $this->postWebhook('payment.captured', 'pay_no_secret', 'order_no_secret')->assertStatus(200);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_no_secret')->exists());
    }

    public function test_authorized_payment_is_not_recorded(): void
    {
        $this->makePaymentHash('order_authorized');

        $this->postWebhook('payment.authorized', 'pay_authorized', 'order_authorized', null, 'authorized')->assertStatus(200);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_authorized')->exists());
    }

    public function test_unknown_order_is_acknowledged(): void
    {
        $this->postWebhook('payment.captured', 'pay_unknown', 'order_unknown')->assertStatus(200);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_unknown')->exists());
    }

    private function makeGateway(string $webhookSecret): CompanyGateway
    {
        $company_gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $company_gateway->gateway_key = self::GATEWAY_KEY;
        $company_gateway->fees_and_limits = new \stdClass();
        $company_gateway->setConfig([
            'apiKey' => 'rzp_test_key',
            'apiSecret' => self::API_SECRET,
            'webhookSecret' => $webhookSecret,
        ]);
        $company_gateway->save();

        return $company_gateway;
    }

    private function makePaymentHash(string $orderId): PaymentHash
    {
        return PaymentHash::query()->create([
            'hash' => Str::random(32),
            'fee_invoice_id' => $this->invoice->id,
            'fee_total' => 0,
            'data' => [
                'amount_with_fee' => 1,
                'credits' => 0,
                'order_id' => $orderId,
                'order_amount' => 100,
                'invoices' => [[
                    'invoice_id' => $this->invoice->hashed_id,
                    'invoice_number' => $this->invoice->number,
                    'amount' => 1,
                ]],
            ],
        ]);
    }

    private function postWebhook(string $event, string $paymentId, string $orderId, ?string $secret = null, string $status = 'captured'): TestResponse
    {
        $payload = json_encode([
            'entity' => 'event',
            'event' => $event,
            'contains' => ['payment'],
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'entity' => 'payment',
                        'order_id' => $orderId,
                        'status' => $status,
                        'amount' => 100,
                        'currency' => 'INR',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            sprintf('/payment_webhook/%s/%s', $this->company->company_key, $this->company_gateway->hashed_id),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, $secret ?? self::WEBHOOK_SECRET),
            ],
            $payload,
        );
    }
}
