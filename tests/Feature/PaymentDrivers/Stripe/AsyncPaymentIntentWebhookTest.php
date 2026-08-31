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
use App\Models\ClientGatewayToken;
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
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
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

    public function testStripeWebhookSubscribesToPaymentAndSetupIntentLifecycleEvents(): void
    {
        $job = new StripeWebhook($this->company->company_key, $this->company_gateway->id);
        $property = new ReflectionProperty($job, 'events');
        $events = $property->getValue($job);

        $this->assertContains('payment_intent.processing', $events);
        $this->assertContains('payment_intent.succeeded', $events);
        $this->assertContains('customer.source.updated', $events);
        $this->assertContains('setup_intent.succeeded', $events);
        $this->assertContains('setup_intent.setup_failed', $events);
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

    public function testAchProcessingAuthorizesTokenAndClearsPendingVerificationMetadata(): void
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $this->company_gateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = 'pm_ach_processing';
        $token->gateway_customer_reference = 'cus_ach_processing';
        $token->meta = (object) [
            'state' => 'unauthorized',
            'next_action' => 'https://verify.stripe.com/pending',
        ];
        $token->save();

        (new PaymentIntentProcessingWebhook(
            ['object' => [
                'id' => 'pi_ach_processing',
                'payment_method' => $token->token,
            ]],
            $this->company->company_key,
            $this->company_gateway->id,
        ))->handle();

        $meta = $token->fresh()->meta;

        $this->assertSame('authorized', $meta->state);
        $this->assertObjectNotHasProperty('mandate_id', $meta);
        $this->assertObjectNotHasProperty('next_action', $meta);
    }

    public function testAchSucceededCreatesAuthorizedTokenWhenProcessingWebhookWasMissed(): void
    {
        $payment_hash = $this->makePaymentHash();
        $payment_intent = PaymentIntent::constructFrom([
            'id' => 'pi_ach_succeeded',
            'object' => 'payment_intent',
            'customer' => 'cus_ach_succeeded',
            'payment_method' => 'pm_ach_succeeded',
            'payment_method_types' => ['us_bank_account'],
            'metadata' => [
                'payment_hash' => $payment_hash->hash,
                'gateway_type_id' => (string) GatewayType::BANK_TRANSFER,
            ],
        ]);

        $this->withStripeAchPaymentMethod(function () use ($payment_hash, $payment_intent): void {
            $this->invokeUpdateAsyncPayment(
                new PaymentIntentWebhook(
                    ['object' => $payment_intent->toArray()],
                    $this->company->company_key,
                    $this->company_gateway->id,
                ),
                $payment_hash,
                $payment_intent,
            );
        });

        $token = ClientGatewayToken::query()->where('token', 'pm_ach_succeeded')->firstOrFail();

        $this->assertSame('authorized', $token->meta->state);
        $this->assertObjectNotHasProperty('mandate_id', $token->meta);
    }


    /**
     * The production incident path: the fee is quoted at initiation and written to the
     * invoice only when the webhook confirms the payment.
     */
    public function testAsyncWebhookWritesTheGatewayFeeToTheInvoice(): void
    {
        $payment_hash = $this->makePaymentHashWithFee(5);
        $payment_intent = $this->makePaymentIntent($payment_hash);

        $starting_amount = (float) $this->invoice->fresh()->amount;

        /** The fee is not on the invoice before the webhook lands. */
        $this->assertFalse(collect($this->invoice->fresh()->line_items)->contains('unit_code', $payment_hash->hash));

        $job = new PaymentIntentWebhook(
            ['object' => $payment_intent->toArray()],
            $this->company->company_key,
            $this->company_gateway->id,
        );

        $this->invokeUpdateAsyncPayment($job, $payment_hash, $payment_intent);

        $invoice = $this->invoice->fresh();
        $fee_lines = collect($invoice->line_items)->where('unit_code', $payment_hash->hash);

        $this->assertCount(1, $fee_lines, 'the webhook did not write the gateway fee to the invoice');
        $this->assertSame('4', (string) $fee_lines->first()->type_id);
        $this->assertEquals(round($starting_amount + 5, 2), round((float) $invoice->amount, 2));
    }

    /**
     * processing then succeeded means two confirmations for one payment hash.
     * Exactly one surcharge must result.
     */
    public function testProcessingThenSucceededWritesTheGatewayFeeOnce(): void
    {
        $payment_hash = $this->makePaymentHashWithFee(5);
        $payment_intent = $this->makePaymentIntent($payment_hash);

        $starting_amount = (float) $this->invoice->fresh()->amount;

        $this->invokeUpdateAsyncPayment(
            new PaymentIntentProcessingWebhook(
                ['object' => $payment_intent->toArray()],
                $this->company->company_key,
                $this->company_gateway->id,
            ),
            $payment_hash,
            $payment_intent,
        );

        (new PaymentIntentWebhook(
            ['object' => $payment_intent->toArray()],
            $this->company->company_key,
            $this->company_gateway->id,
        ))->handle();

        $invoice = $this->invoice->fresh();
        $fee_lines = collect($invoice->line_items)->where('unit_code', $payment_hash->hash);

        $this->assertCount(1, $fee_lines, 'two confirmations produced two surcharges');
        $this->assertEquals(round($starting_amount + 5, 2), round((float) $invoice->amount, 2));
    }

    /** Redelivery of the same succeeded event must not add a second surcharge. */
    public function testRedeliveredSucceededWebhookDoesNotDuplicateTheGatewayFee(): void
    {
        $payment_hash = $this->makePaymentHashWithFee(5);
        $payment_intent = $this->makePaymentIntent($payment_hash);

        $starting_amount = (float) $this->invoice->fresh()->amount;

        foreach (range(1, 3) as $ignored) {
            $this->invokeUpdateAsyncPayment(
                new PaymentIntentWebhook(
                    ['object' => $payment_intent->toArray()],
                    $this->company->company_key,
                    $this->company_gateway->id,
                ),
                $payment_hash->fresh(),
                $payment_intent,
            );
        }

        $invoice = $this->invoice->fresh();

        $this->assertCount(1, collect($invoice->line_items)->where('unit_code', $payment_hash->hash));
        $this->assertEquals(round($starting_amount + 5, 2), round((float) $invoice->amount, 2));
        $this->assertSame(1, Payment::query()->where('transaction_reference', $payment_intent->id)->count());
    }

    private function makePaymentHashWithFee(float $fee): PaymentHash
    {
        return PaymentHash::query()->create([
            'company_id' => $this->company->id,
            'hash' => Str::random(32),
            'fee_invoice_id' => $this->invoice->id,
            'fee_total' => $fee,
            'data' => [
                'amount_with_fee' => 1 + $fee,
                'credits' => 0,
                'fee_net' => $fee,
                'invoices' => [[
                    'invoice_id' => $this->invoice->hashed_id,
                    'invoice_number' => $this->invoice->number,
                    'amount' => 1,
                ]],
            ],
        ]);
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

    private function withStripeAchPaymentMethod(callable $callback): void
    {
        ApiRequestor::setHttpClient(new class () implements ClientInterface {
            public function request(
                $method,
                $absUrl,
                $headers,
                $params,
                $hasFile,
                $apiMode = 'v1',
                $maxNetworkRetries = null,
            ): array {
                if (str_contains($absUrl, '/v1/customers/')) {
                    $response = [
                        'id' => 'cus_ach_succeeded',
                        'object' => 'customer',
                        'default_source' => null,
                    ];
                } else {
                    $response = [
                        'id' => 'pm_ach_succeeded',
                        'object' => 'payment_method',
                        'customer' => 'cus_ach_succeeded',
                        'type' => 'us_bank_account',
                        'us_bank_account' => [
                            'bank_name' => 'Succeeded Bank',
                            'last4' => '6789',
                        ],
                    ];
                }

                return [json_encode($response, JSON_THROW_ON_ERROR), 200, []];
            }
        });

        try {
            $callback();
        } finally {
            ApiRequestor::setHttpClient(null);
        }
    }
}
