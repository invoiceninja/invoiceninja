<?php

namespace Tests\Unit\PaymentDrivers\Stripe;

use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\PaymentDrivers\Stripe\Jobs\RefundFailed;
use App\PaymentDrivers\StripePaymentDriver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Modules\Admin\Http\Controllers\StripeConnectWebhookController;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class RefundFailedTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        $this->gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $this->gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $this->gateway->accepted_credit_cards = 0;
        $this->gateway->fees_and_limits = new \stdClass();
        $this->gateway->setConfig([
            'webhookSecret' => 'whsec_refund_test',
            'account_id' => 'acct_refund_test',
        ]);
        $this->gateway->save();
    }

    #[DataProvider('paymentCases')]
    public function testAppendsNoteOnceWithoutChangingFinancialFields(string $reference, ?string $notes, int $status): void
    {
        $payment = $this->makePayment($reference);
        $payment->private_notes = $notes;
        $payment->status_id = $status;
        $payment->saveQuietly();
        $before = $payment->fresh()->getAttributes();

        $job = new RefundFailed($this->refund(), $this->company->company_key, $this->gateway->id);
        $job->handle();
        $job->handle();

        $after = $payment->fresh()->getAttributes();
        $expected = ($notes ? rtrim($notes, "\n") . "\n" : '')
            . 'Stripe refund failed to be processed. Refund ID: re_refund_test.';
        $this->assertSame($expected, $after['private_notes']);
        unset($before['private_notes'], $before['updated_at'], $after['private_notes'], $after['updated_at']);
        $this->assertSame($before, $after);
    }

    public static function paymentCases(): array
    {
        return [
            ['ch_refund_test', null, Payment::STATUS_COMPLETED],
            ['pi_refund_test', 'Existing note', Payment::STATUS_REFUNDED],
            ['pi_refund_test', "Existing note\n", Payment::STATUS_PARTIALLY_REFUNDED],
        ];
    }

    public function testOverlappingRefundIsReleasedAndRetryDoesNotDuplicateNote(): void
    {
        $payment = $this->makePayment('pi_refund_test');
        $job = Mockery::mock(RefundFailed::class, [
            $this->refund(), $this->company->company_key, $this->gateway->id,
        ])->makePartial();
        $job->shouldReceive('release')->once()->with(5);
        $middleware = $job->middleware()[0];
        $lock = Cache::lock($middleware->getLockKey($job), 60);
        $this->assertTrue($lock->get());

        try {
            $middleware->handle($job, function () {
                $this->fail('An overlapping job must not run.');
            });
            $this->assertSame('Original note', $payment->fresh()->private_notes);
        } finally {
            $lock->release();
        }

        $middleware->handle($job, fn ($job) => $job->handle());
        $middleware->handle($job, fn ($job) => $job->handle());

        $this->assertSame(
            "Original note\nStripe refund failed to be processed. Refund ID: re_refund_test.",
            $payment->fresh()->private_notes,
        );
    }

    public function testDistinctFailedRefundsEachAppendANote(): void
    {
        $payment = $this->makePayment('ch_refund_test');
        $refund = $this->refund();
        (new RefundFailed($refund, $this->company->company_key, $this->gateway->id))->handle();
        $refund['id'] = 're_second_refund';
        (new RefundFailed($refund, $this->company->company_key, $this->gateway->id))->handle();

        $this->assertSame(
            "Original note\nStripe refund failed to be processed. Refund ID: re_refund_test."
                . "\nStripe refund failed to be processed. Refund ID: re_second_refund.",
            $payment->fresh()->private_notes,
        );
    }

    public function testArchivedPaymentReceivesNoteWithoutBeingRestored(): void
    {
        $payment = $this->makePayment('ch_refund_test');
        $payment->delete();

        (new RefundFailed($this->refund(), $this->company->company_key, $this->gateway->id))->handle();

        $payment = $payment->fresh();
        $this->assertTrue($payment->trashed());
        $this->assertSame(
            "Original note\nStripe refund failed to be processed. Refund ID: re_refund_test.",
            $payment->private_notes,
        );
    }

    public function testDeletedPaymentDoesNotReceiveNote(): void
    {
        $payment = $this->makePayment('ch_refund_test');
        $payment->is_deleted = true;
        $payment->saveQuietly();
        $payment->delete();

        (new RefundFailed($this->refund(), $this->company->company_key, $this->gateway->id))->handle();

        $this->assertSame('Original note', $payment->fresh()->private_notes);
    }

    public function testDoesNotUpdatePaymentFromAnotherGatewayOrCompany(): void
    {
        $payment = $this->makePayment('ch_refund_test');
        $other_gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $other_gateway->gateway_key = $this->gateway->gateway_key;
        $other_gateway->save();

        (new RefundFailed($this->refund(), $this->company->company_key, $other_gateway->id))->handle();
        (new RefundFailed($this->refund(), 'unknown_company_key', $this->gateway->id))->handle();

        $this->assertSame('Original note', $payment->fresh()->private_notes);
    }

    public function testUnmatchedRefundDoesNotChangeNotes(): void
    {
        $payment = $this->makePayment('ch_other_payment');
        (new RefundFailed($this->refund(), $this->company->company_key, $this->gateway->id))->handle();
        $this->assertSame('Original note', $payment->fresh()->private_notes);
    }

    public function testStandardWebhookDispatchesRefundFailed(): void
    {
        Queue::fake();
        $request = $this->signedRequest(PaymentWebhookRequest::class);
        $response = (new StripePaymentDriver($this->gateway))->processWebhookRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertRefundDispatched();
    }

    public function testConnectWebhookDispatchesRefundFailed(): void
    {
        if (!class_exists(StripeConnectWebhookController::class)) {
            $this->markTestSkipped('Admin module is not installed.');
        }

        Queue::fake();
        $this->makePayment('pi_refund_test')->delete();
        config(['ninja.ninja_connect_secret_v2' => 'whsec_refund_test']);
        $database = config('database.default');

        $response = (new StripeConnectWebhookController())($this->signedRequest(Request::class));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($database, config('database.default'));
        $this->assertRefundDispatched();
    }

    public function testConnectWebhookRejectsAccountMismatch(): void
    {
        if (!class_exists(StripeConnectWebhookController::class)) {
            $this->markTestSkipped('Admin module is not installed.');
        }

        Queue::fake();
        $this->makePayment('pi_refund_test');
        config(['ninja.ninja_connect_secret_v2' => 'whsec_refund_test']);

        (new StripeConnectWebhookController())($this->signedRequest(Request::class, 'acct_other'));

        Queue::assertNotPushed(RefundFailed::class);
    }

    private function assertRefundDispatched(): void
    {
        Queue::assertPushed(RefundFailed::class, fn ($job) =>
            $job->stripe_request === $this->refund()
            && $job->company_key === $this->company->company_key
            && $job->company_gateway_id === $this->gateway->id);
    }

    private function makePayment(string $reference): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_gateway_id' => $this->gateway->id,
            'transaction_reference' => $reference,
            'is_manual' => false,
            'private_notes' => 'Original note',
        ]);
    }

    private function refund(): array
    {
        return [
            'id' => 're_refund_test',
            'object' => 'refund',
            'charge' => 'ch_refund_test',
            'payment_intent' => 'pi_refund_test',
            'status' => 'failed',
        ];
    }

    private function signedRequest(string $request_class, string $account_id = 'acct_refund_test'): Request
    {
        $payload = json_encode([
            'id' => 'evt_refund_failed',
            'object' => 'event',
            'type' => 'refund.failed',
            'account' => $account_id,
            'data' => ['object' => $this->refund()],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_refund_test');

        return $request_class::create('/api/connect', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }
}
