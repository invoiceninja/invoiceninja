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

use App\Factory\CompanyGatewayFactory;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentProcessingWebhook;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentWebhook;
use App\PaymentDrivers\Stripe\Jobs\StripeWebhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Stripe\PaymentIntent;
use Tests\MockAccountData;
use Tests\TestCase;

class AsyncPaymentIntentWebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        Queue::fake();

        $this->company_gateway = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $this->company_gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $this->company_gateway->accepted_credit_cards = 0;
        $this->company_gateway->fees_and_limits = new \stdClass();
        $this->company_gateway->setConfig([
            'apiKey' => 'sk_test_async_webhooks',
            'publishableKey' => 'pk_test_async_webhooks',
        ]);
        $this->company_gateway->save();
    }

    public function testAsyncPaymentTypesAreConsistentAcrossWebhookJobs(): void
    {
        $expected = [
            'us_bank_account' => PaymentType::ACH,
            'bacs_debit' => PaymentType::BACS,
            'au_becs_debit' => PaymentType::BECS,
            'acss_debit' => PaymentType::ACSS,
            'sepa_debit' => PaymentType::SEPA,
            'customer_balance' => PaymentType::STRIPE_BANK_TRANSFER,
        ];

        $this->assertSame(
            $expected,
            (new ReflectionClass(PaymentIntentProcessingWebhook::class))->getConstant('ASYNC_PAYMENT_TYPES'),
        );
        $this->assertSame(
            $expected,
            (new ReflectionClass(PaymentIntentWebhook::class))->getConstant('ASYNC_PAYMENT_TYPES'),
        );
    }

    public function testStripeWebhookSubscribesToProcessingAndSucceededEvents(): void
    {
        $job = new StripeWebhook($this->company->company_key, $this->company_gateway->id);
        $property = new ReflectionProperty($job, 'events');
        $events = $property->getValue($job);

        $this->assertContains('payment_intent.processing', $events);
        $this->assertContains('payment_intent.succeeded', $events);
    }

    public function testBacsProcessingCreatesPendingPaymentAndSucceededCompletesIt(): void
    {
        $payment_hash = $this->makePaymentHash();
        $payment_intent = $this->makePaymentIntent($payment_hash);
        $processing_job = new PaymentIntentProcessingWebhook(
            ['object' => $payment_intent->toArray()],
            $this->company->company_key,
            $this->company_gateway->id,
        );

        $this->invokeUpdateAsyncPayment($processing_job, $payment_hash, $payment_intent);

        $payment = Payment::query()
            ->where('transaction_reference', $payment_intent->id)
            ->firstOrFail();

        $this->assertSame(Payment::STATUS_PENDING, $payment->status_id);
        $this->assertSame(PaymentType::BACS, $payment->type_id);
        $this->assertSame(GatewayType::BACS, $payment->gateway_type_id);
        $this->assertSame($payment->id, $payment_hash->fresh()->payment_id);

        (new PaymentIntentWebhook(
            ['object' => $payment_intent->toArray()],
            $this->company->company_key,
            $this->company_gateway->id,
        ))->handle();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
        $this->assertSame(
            1,
            Payment::query()->where('transaction_reference', $payment_intent->id)->count(),
        );
    }

    public function testBacsSucceededCreatesCompletedPaymentWhenProcessingWasMissed(): void
    {
        $payment_hash = $this->makePaymentHash();
        $payment_intent = $this->makePaymentIntent($payment_hash);
        $succeeded_job = new PaymentIntentWebhook(
            ['object' => $payment_intent->toArray()],
            $this->company->company_key,
            $this->company_gateway->id,
        );

        $this->invokeUpdateAsyncPayment($succeeded_job, $payment_hash, $payment_intent);

        $payment = Payment::query()
            ->where('transaction_reference', $payment_intent->id)
            ->firstOrFail();

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->status_id);
        $this->assertSame(PaymentType::BACS, $payment->type_id);
        $this->assertSame($payment->id, $payment_hash->fresh()->payment_id);
    }

    private function makePaymentHash(): PaymentHash
    {
        return PaymentHash::query()->create([
            'company_id' => $this->company->id,
            'hash' => Str::random(32),
            'fee_invoice_id' => $this->invoice->id,
            'fee_total' => 0,
            'data' => [
                'amount_with_fee' => 1,
                'credits' => 0,
                'invoices' => [[
                    'invoice_id' => $this->invoice->hashed_id,
                    'invoice_number' => $this->invoice->number,
                    'amount' => 1,
                ]],
            ],
        ]);
    }

    private function makePaymentIntent(PaymentHash $payment_hash): PaymentIntent
    {
        return PaymentIntent::constructFrom([
            'id' => 'pi_bacs_' . Str::lower(Str::random(16)),
            'object' => 'payment_intent',
            'customer' => 'cus_bacs_test',
            'payment_method' => 'pm_bacs_test',
            'payment_method_types' => ['bacs_debit'],
            'metadata' => [
                'payment_hash' => $payment_hash->hash,
                'gateway_type_id' => (string) GatewayType::BACS,
            ],
        ]);
    }

    private function invokeUpdateAsyncPayment(
        PaymentIntentProcessingWebhook|PaymentIntentWebhook $job,
        PaymentHash $payment_hash,
        PaymentIntent $payment_intent,
    ): void {
        $method = new ReflectionMethod($job, 'updateAsyncPayment');
        $method->invoke(
            $job,
            $payment_hash,
            $this->client,
            [
                'gateway_type_id' => GatewayType::BACS,
                'transaction_reference' => $payment_intent->id,
                'customer' => $payment_intent->customer,
                'payment_method' => $payment_intent->payment_method,
            ],
            $payment_intent,
        );
    }
}
