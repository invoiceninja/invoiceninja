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
use App\PaymentDrivers\Razorpay\Jobs\PaymentCapturedWebhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
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

    public function testCapturedWebhookRecordsCompletedPayment(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $this->makePaymentHash('order_captured');
        $balance = (float) $this->invoice->fresh()->balance;

        $this->postWebhook('payment.captured', 'pay_captured', 'order_captured')->assertStatus(200);
        $this->runAllPushedCapturedWebhookJobs();

        $payment = Payment::query()->where('transaction_reference', 'pay_captured')->first();

        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertSame($this->client->id, $payment->client_id);
        $this->assertEqualsWithDelta($balance - 1, (float) $this->invoice->fresh()->balance, 0.001);
    }

    public function testOrderPaidWebhookRecordsCompletedPayment(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $this->makePaymentHash('order_paid');

        $this->postWebhook('order.paid', 'pay_order_paid', 'order_paid')->assertStatus(200);
        $this->runAllPushedCapturedWebhookJobs();

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_order_paid')->count());
    }

    public function testRepeatedWebhooksRecordOnePayment(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $this->makePaymentHash('order_repeat');

        $this->postWebhook('payment.captured', 'pay_repeat', 'order_repeat')->assertStatus(200);
        $this->postWebhook('order.paid', 'pay_repeat', 'order_repeat')->assertStatus(200);
        $this->postWebhook('payment.captured', 'pay_repeat', 'order_repeat')->assertStatus(200);
        $this->runAllPushedCapturedWebhookJobs();

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_repeat')->count());
    }

    public function testBrowserReturnAfterWebhookDoesNotRecordASecondPayment(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

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

        $this->runAllPushedCapturedWebhookJobs();

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_return')->count());
    }

    public function testInvalidSignatureIsRejected(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $this->makePaymentHash('order_bad_signature');

        $this->postWebhook('payment.captured', 'pay_bad_signature', 'order_bad_signature', 'not_the_webhook_secret')
            ->assertStatus(400);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_bad_signature')->exists());
        Queue::assertNotPushed(PaymentCapturedWebhook::class);
    }

    public function testWebhookIsIgnoredWithoutAWebhookSecret(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $this->company_gateway = $this->makeGateway('');
        $this->makePaymentHash('order_no_secret');

        $this->postWebhook('payment.captured', 'pay_no_secret', 'order_no_secret')->assertStatus(200);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_no_secret')->exists());
        Queue::assertNotPushed(PaymentCapturedWebhook::class);
    }

    public function testAuthorizedPaymentIsNotRecorded(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $this->makePaymentHash('order_authorized');

        $this->postWebhook('payment.authorized', 'pay_authorized', 'order_authorized', null, 'authorized')->assertStatus(200);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_authorized')->exists());
        Queue::assertNotPushed(PaymentCapturedWebhook::class);
    }

    public function testUnknownOrderIsAcknowledged(): void
    {
        $this->postWebhook('payment.captured', 'pay_unknown', 'order_unknown')->assertStatus(200);

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_unknown')->exists());
    }

    public function testCapturedWebhookDispatchesDelayedJob(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);
        $this->makePaymentHash('order_queued');
        $expected_delay = now()->addSeconds(5);

        $this->postWebhook('payment.captured', 'pay_queued', 'order_queued')->assertStatus(200);

        Queue::assertPushed(PaymentCapturedWebhook::class, function ($job) use ($expected_delay) {
            return $job->entity['id'] === 'pay_queued'
                && $job->entity['order_id'] === 'order_queued'
                && $job->company_key === $this->company->company_key
                && $job->company_gateway_id === $this->company_gateway->id
                && abs($job->delay->getTimestamp() - $expected_delay->getTimestamp()) <= 1;
        });
        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_queued')->exists());
    }

    public function testBrowserPaymentBeforeQueuedWebhookRecordsOnePayment(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);
        $payment_hash = $this->makePaymentHash('order_browser_first');
        $balance = (float) $this->invoice->fresh()->balance;

        $this->postWebhook('payment.captured', 'pay_browser_first', 'order_browser_first')->assertStatus(200);

        $driver = $this->company_gateway->driver($this->client)->init()->setPaymentHash($payment_hash);
        (new Hosted($driver))->processSuccessfulPayment('pay_browser_first');

        Queue::assertPushed(PaymentCapturedWebhook::class, function ($job) {
            $queued_job = unserialize(serialize($job));
            $queued_job->handle();
            $queued_job->handle();

            return true;
        });

        $this->assertSame(1, Payment::query()->where('transaction_reference', 'pay_browser_first')->count());
        $this->assertEqualsWithDelta($balance - 1, (float) $this->invoice->fresh()->balance, 0.001);
    }

    public function testRequestsIncludePaymentHashNotes(): void
    {
        $payment_hash = $this->makePaymentHash('order_notes');
        $driver = $this->company_gateway->driver($this->client)->setPaymentHash($payment_hash);
        $hosted = new Hosted($driver);
        $order_api = \Mockery::mock(\Razorpay\Api\Order::class);
        $order_api->shouldReceive('create')->once()->with(\Mockery::on(function ($payload) use ($payment_hash) {
            return $payload['notes']['payment_hash'] === $payment_hash->hash
                && $payload['amount'] === 100
                && $payload['currency'] === $this->client->currency()->code;
        }))->andReturn((object) ['id' => 'order_notes', 'amount' => 100]);
        $driver->gateway = new class ($order_api) extends \Razorpay\Api\Api {
            public function __construct(public \Razorpay\Api\Order $order) {}
        };

        $data = $hosted->paymentData([]);

        $this->assertSame($payment_hash->hash, $data['options']['notes']['payment_hash']);
    }

    public function testPaymentNotesTakePriorityOverOrderLookup(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $other_hash = $this->makePaymentHash('order_metadata');
        $payment_hash = $this->makePaymentHash('order_metadata');

        $this->postWebhook('payment.captured', 'pay_metadata', 'order_metadata', payment_notes: ['payment_hash' => $payment_hash->hash])
            ->assertStatus(200);
        $this->runAllPushedCapturedWebhookJobs();

        $payment = Payment::query()->where('transaction_reference', 'pay_metadata')->firstOrFail();
        $this->assertSame($payment->id, $payment_hash->fresh()->payment_id);
        $this->assertNull($other_hash->fresh()->payment_id);
    }

    public function testOrderNotesRehydratePaymentHash(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $other_hash = $this->makePaymentHash('order_order_notes');
        $payment_hash = $this->makePaymentHash('order_order_notes');

        $this->postWebhook('order.paid', 'pay_order_notes', 'order_order_notes', order_notes: ['payment_hash' => $payment_hash->hash])
            ->assertStatus(200);
        $this->runAllPushedCapturedWebhookJobs();

        $payment = Payment::query()->where('transaction_reference', 'pay_order_notes')->firstOrFail();
        $this->assertSame($payment->id, $payment_hash->fresh()->payment_id);
        $this->assertNull($other_hash->fresh()->payment_id);
    }

    public function testMetadataForDifferentOrderIsIgnored(): void
    {
        Queue::fake([PaymentCapturedWebhook::class]);

        $payment_hash = $this->makePaymentHash('order_expected');

        $this->postWebhook('payment.captured', 'pay_mismatch', 'order_different', payment_notes: ['payment_hash' => $payment_hash->hash])
            ->assertStatus(200);
        $this->runAllPushedCapturedWebhookJobs();

        $this->assertFalse(Payment::query()->where('transaction_reference', 'pay_mismatch')->exists());
    }

    private function runAllPushedCapturedWebhookJobs(): void
    {
        foreach (Queue::pushed(PaymentCapturedWebhook::class) as $job) {
            $queued_job = unserialize(serialize($job));
            $queued_job->handle();
        }
    }

    private function makeGateway(string $webhook_secret): CompanyGateway
    {
        $company_gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $company_gateway->gateway_key = self::GATEWAY_KEY;
        $company_gateway->fees_and_limits = new \stdClass();
        $company_gateway->setConfig([
            'apiKey' => 'rzp_test_key',
            'apiSecret' => self::API_SECRET,
            'webhookSecret' => $webhook_secret,
        ]);
        $company_gateway->save();

        return $company_gateway;
    }

    private function makePaymentHash(string $order_id): PaymentHash
    {
        return PaymentHash::query()->create([
            'hash' => Str::random(32),
            'fee_invoice_id' => $this->invoice->id,
            'fee_total' => 0,
            'data' => [
                'amount_with_fee' => 1,
                'credits' => 0,
                'order_id' => $order_id,
                'order_amount' => 100,
                'invoices' => [[
                    'invoice_id' => $this->invoice->hashed_id,
                    'invoice_number' => $this->invoice->number,
                    'amount' => 1,
                ]],
            ],
        ]);
    }

    private function postWebhook(string $event, string $payment_id, string $order_id, ?string $secret = null, string $status = 'captured', array $payment_notes = [], array $order_notes = []): TestResponse
    {
        $payload = json_encode([
            'entity' => 'event',
            'event' => $event,
            'contains' => ['payment'],
            'payload' => [
                'order' => ['entity' => ['id' => $order_id, 'notes' => $order_notes]],
                'payment' => [
                    'entity' => [
                        'id' => $payment_id,
                        'entity' => 'payment',
                        'order_id' => $order_id,
                        'status' => $status,
                        'amount' => 100,
                        'currency' => 'INR',
                        'notes' => $payment_notes,
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
