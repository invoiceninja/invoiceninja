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

namespace Tests\Feature\PaymentDrivers\GoCardless;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\GoCardless\Jobs\GoCardlessWebhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class GoCardlessWebhookJobTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $gocardless_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
        $cg->require_cvv = false;
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->update_details = false;
        $cg->config = encrypt(json_encode([
            'accessToken' => 'fake_access_token',
            'webhookSecret' => 'shh',
            'testMode' => true,
        ]));
        $cg->fees_and_limits = [];
        $cg->save();

        $this->gocardless_gateway = $cg;
    }

    private function makePayment(string $reference, int $status = Payment::STATUS_PENDING): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'transaction_reference' => $reference,
            'status_id' => $status,
            'amount' => 10,
        ]);
    }

    private function dispatchEvents(array $events): TestableGoCardlessWebhook
    {
        $job = new TestableGoCardlessWebhook(
            $events,
            $this->company->company_key,
            $this->gocardless_gateway->id,
        );
        $job->handle();

        return $job;
    }

    private function makeMandate(string $reference, string $state = 'pending'): ClientGatewayToken
    {
        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $this->gocardless_gateway->id;
        $token->gateway_type_id = GatewayType::DIRECT_DEBIT;
        $token->token = $reference;
        $token->gateway_customer_reference = 'CU_TEST';
        $token->meta = (object) [
            'brand' => 'Test Bank',
            'last4' => '1234',
            'state' => $state,
        ];
        $token->save();

        return $token;
    }

    public function test_missing_company_gateway_returns_without_throwing(): void
    {
        $job = new GoCardlessWebhook(
            [['id' => 'EV1', 'resource_type' => 'payments', 'action' => 'confirmed', 'links' => ['payment' => 'PMx']]],
            $this->company->company_key,
            99999999
        );

        $job->handle();

        $this->assertTrue(true);
    }

    public function test_confirmed_event_sets_status_completed(): void
    {
        $payment = $this->makePayment('PM_CONF_1', Payment::STATUS_PENDING);

        $this->dispatchEvents([[
            'id' => 'EV1',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['payment' => 'PM_CONF_1'],
        ]]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }

    public function test_paid_out_event_sets_status_completed(): void
    {
        $payment = $this->makePayment('PM_PAIDOUT_1', Payment::STATUS_PENDING);

        $this->dispatchEvents([[
            'id' => 'EV2',
            'resource_type' => 'payments',
            'action' => 'paid_out',
            'links' => ['payment' => 'PM_PAIDOUT_1', 'payout' => 'PO1'],
        ]]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }

    public function test_paid_out_action_on_another_resource_does_not_complete_a_payment(): void
    {
        $payment = $this->makePayment('PM_NON_PAYMENT_PAIDOUT', Payment::STATUS_PENDING);

        $this->dispatchEvents([[
            'id' => 'EV_NON_PAYMENT_PAIDOUT',
            'resource_type' => 'payouts',
            'action' => 'paid_out',
            'links' => ['payment' => 'PM_NON_PAYMENT_PAIDOUT'],
        ]]);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status_id);
    }

    #[DataProvider('settled_payment_state_provider')]
    public function test_settlement_events_do_not_regress_terminal_payment_states(string $action, int $status_id): void
    {
        $payment = $this->makePayment("PM_TERMINAL_{$action}_{$status_id}", $status_id);

        $this->dispatchEvents([[
            'id' => "EV_TERMINAL_{$action}_{$status_id}",
            'resource_type' => 'payments',
            'action' => $action,
            'links' => ['payment' => $payment->transaction_reference],
        ]]);

        $this->assertSame($status_id, $payment->fresh()->status_id);
    }

    public static function settled_payment_state_provider(): array
    {
        return [
            'confirmed completed' => ['confirmed', Payment::STATUS_COMPLETED],
            'confirmed partially refunded' => ['confirmed', Payment::STATUS_PARTIALLY_REFUNDED],
            'confirmed refunded' => ['confirmed', Payment::STATUS_REFUNDED],
            'paid out completed' => ['paid_out', Payment::STATUS_COMPLETED],
            'paid out partially refunded' => ['paid_out', Payment::STATUS_PARTIALLY_REFUNDED],
            'paid out refunded' => ['paid_out', Payment::STATUS_REFUNDED],
        ];
    }

    #[DataProvider('payment_scheme_provider')]
    public function test_created_event_sets_the_payment_type_for_every_supported_scheme(string $scheme, int $payment_type_id): void
    {
        $payment = $this->makePayment("PM_SCHEME_{$scheme}");
        $payment->type_id = PaymentType::DIRECT_DEBIT;
        $payment->saveQuietly();

        $this->dispatchEvents([[
            'id' => "EV_SCHEME_{$scheme}",
            'resource_type' => 'payments',
            'action' => 'created',
            'details' => ['scheme' => $scheme],
            'links' => ['payment' => $payment->transaction_reference],
        ]]);

        $this->assertSame($payment_type_id, $payment->fresh()->type_id);
    }

    public static function payment_scheme_provider(): array
    {
        return [
            'ACH' => ['ach', PaymentType::ACH],
            'Autogiro' => ['autogiro', PaymentType::DIRECT_DEBIT],
            'Bacs' => ['bacs', PaymentType::BACS],
            'BECS' => ['becs', PaymentType::BECS],
            'BECS NZ' => ['becs_nz', PaymentType::DIRECT_DEBIT],
            'Betalingsservice' => ['betalingsservice', PaymentType::DIRECT_DEBIT],
            'Faster Payments' => ['faster_payments', PaymentType::INSTANT_BANK_PAY],
            'PAD' => ['pad', PaymentType::ACSS],
            'PayTo' => ['pay_to', PaymentType::DIRECT_DEBIT],
            'SEPA Core' => ['sepa_core', PaymentType::SEPA],
            'SEPA Credit Transfer' => ['sepa_credit_transfer', PaymentType::INSTANT_BANK_PAY],
            'SEPA Instant Credit Transfer' => ['sepa_instant_credit_transfer', PaymentType::INSTANT_BANK_PAY],
        ];
    }

    public function test_created_event_without_a_scheme_does_not_change_the_payment_type(): void
    {
        $payment = $this->makePayment('PM_SCHEME_MISSING');
        $payment->type_id = PaymentType::BACS;
        $payment->saveQuietly();

        $this->dispatchEvents([[
            'id' => 'EV_SCHEME_MISSING',
            'resource_type' => 'payments',
            'action' => 'created',
            'links' => ['payment' => $payment->transaction_reference],
        ]]);

        $this->assertSame(PaymentType::BACS, $payment->fresh()->type_id);
    }

    public function test_submitted_event_keeps_the_payment_pending(): void
    {
        $payment = $this->makePayment('PM_SUBMITTED');

        $this->dispatchEvents([[
            'id' => 'EV_SUBMITTED',
            'resource_type' => 'payments',
            'action' => 'submitted',
            'links' => ['payment' => $payment->transaction_reference],
        ]]);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status_id);
    }

    public function test_late_failure_settled_event_deletes_the_applied_payment_and_sets_status_failed(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $invoice = $this->invoice->fresh();
        $original_balance = (float) $invoice->balance;
        $applied_amount = min(10.0, $original_balance);
        $invoice->balance = $original_balance - $applied_amount;
        $invoice->paid_to_date = $applied_amount;
        $invoice->saveQuietly();

        $payment = $this->makePayment('PM_LATE_SETTLED_1', Payment::STATUS_COMPLETED);
        $payment->amount = $applied_amount;
        $payment->applied = $applied_amount;
        $payment->saveQuietly();
        $payment->invoices()->attach($invoice->id, ['amount' => $applied_amount]);

        $this->dispatchEvents([[
            'id' => 'EV_LATE_SETTLED',
            'resource_type' => 'payments',
            'action' => 'late_failure_settled',
            'links' => ['payment' => 'PM_LATE_SETTLED_1'],
        ]]);

        $failed_payment = Payment::withTrashed()->findOrFail($payment->id);

        $this->assertTrue($failed_payment->trashed());
        $this->assertSame(Payment::STATUS_FAILED, $failed_payment->status_id);
        $this->assertEqualsWithDelta($original_balance, (float) $invoice->fresh()->balance, 0.001);
        Bus::assertDispatched(PaymentFailedMailer::class, 1);
    }

    public function test_confirmed_does_not_regress_refunded_payment(): void
    {
        $payment = $this->makePayment('PM_REF_1', Payment::STATUS_REFUNDED);

        $this->dispatchEvents([[
            'id' => 'EV3',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['payment' => 'PM_REF_1'],
        ]]);

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status_id);
    }

    public function test_confirmed_without_links_payment_key_does_not_throw(): void
    {
        $this->dispatchEvents([[
            'id' => 'EV4',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['mandate' => 'MD1'],
        ]]);

        $this->assertTrue(true);
    }

    public function test_failed_event_marks_payment_failed_and_dispatches_mailer(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('PM_FAIL_1', Payment::STATUS_COMPLETED);

        $this->dispatchEvents([[
            'id' => 'EV5',
            'resource_type' => 'payments',
            'action' => 'failed',
            'links' => ['payment' => 'PM_FAIL_1'],
            'details' => ['description' => 'Insufficient funds'],
        ]]);

        $failed_payment = Payment::withTrashed()->findOrFail($payment->id);

        $this->assertTrue($failed_payment->trashed());
        $this->assertSame(Payment::STATUS_FAILED, $failed_payment->status_id);
        Bus::assertDispatched(PaymentFailedMailer::class, 1);
    }

    public function test_retryable_failed_event_keeps_payment_pending_until_it_is_confirmed(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('PM_RETRYABLE_FAILURE', Payment::STATUS_PENDING);

        $this->dispatchEvents([[
            'id' => 'EV_RETRYABLE_FAILURE',
            'resource_type' => 'payments',
            'action' => 'failed',
            'links' => ['payment' => $payment->transaction_reference],
            'details' => [
                'description' => 'Insufficient funds',
                'will_attempt_retry' => true,
            ],
        ]]);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status_id);
        $this->assertFalse($payment->fresh()->trashed());
        Bus::assertNotDispatched(PaymentFailedMailer::class);

        $this->dispatchEvents([[
            'id' => 'EV_RETRYABLE_FAILURE_CONFIRMED',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['payment' => $payment->transaction_reference],
        ]]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
        Bus::assertNotDispatched(PaymentFailedMailer::class);
    }

    public function test_failed_event_is_idempotent_on_retry(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('PM_FAIL_2', Payment::STATUS_FAILED);

        $this->dispatchEvents([[
            'id' => 'EV6',
            'resource_type' => 'payments',
            'action' => 'failed',
            'links' => ['payment' => 'PM_FAIL_2'],
            'details' => ['description' => 'Already failed'],
        ]]);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status_id);
        Bus::assertNotDispatched(PaymentFailedMailer::class);
    }

    public function test_cancelled_event_deletes_a_pending_payment_and_marks_it_failed(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('PM_CANCELLED');

        $this->dispatchEvents([[
            'id' => 'EV_CANCELLED',
            'resource_type' => 'payments',
            'action' => 'cancelled',
            'links' => ['payment' => $payment->transaction_reference],
            'details' => ['description' => 'Payment cancelled'],
        ]]);

        $cancelled_payment = Payment::withTrashed()->findOrFail($payment->id);

        $this->assertTrue($cancelled_payment->trashed());
        $this->assertSame(Payment::STATUS_FAILED, $cancelled_payment->status_id);
        Bus::assertDispatched(PaymentFailedMailer::class, 1);
    }

    public function test_failure_event_without_a_local_payment_does_not_dispatch_failure_mail(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $this->dispatchEvents([[
            'id' => 'EV_PAYMENT_MISSING',
            'resource_type' => 'payments',
            'action' => 'failed',
            'links' => ['payment' => 'PM_DOES_NOT_EXIST'],
        ]]);

        Bus::assertNotDispatched(PaymentFailedMailer::class);
    }

    #[DataProvider('mandate_state_provider')]
    public function test_mandate_events_update_the_stored_mandate_state(string $action, string $expected_state): void
    {
        $token = $this->makeMandate("MD_{$action}");

        $this->dispatchEvents([[
            'id' => "EV_MD_{$action}",
            'resource_type' => 'mandates',
            'action' => $action,
            'links' => ['mandate' => $token->token],
        ]]);

        $this->assertSame($expected_state, $token->fresh()->meta->state);
    }

    public static function mandate_state_provider(): array
    {
        return [
            'active' => ['active', 'authorized'],
            'cancelled' => ['cancelled', 'cancelled'],
            'expired' => ['expired', 'expired'],
            'failed' => ['failed', 'failed'],
        ];
    }

    public function test_non_terminal_mandate_event_preserves_the_existing_state(): void
    {
        $token = $this->makeMandate('MD_SUBMITTED', 'authorized');

        $this->dispatchEvents([[
            'id' => 'EV_MD_SUBMITTED',
            'resource_type' => 'mandates',
            'action' => 'submitted',
            'links' => ['mandate' => $token->token],
        ]]);

        $this->assertSame('authorized', $token->fresh()->meta->state);
    }

    public function test_mandate_event_is_scoped_to_the_current_company_gateway(): void
    {
        $token = $this->makeMandate('MD_OTHER_GATEWAY');
        $other_gateway = $this->gocardless_gateway->replicate();
        $other_gateway->save();
        $token->company_gateway_id = $other_gateway->id;
        $token->saveQuietly();

        $this->dispatchEvents([[
            'id' => 'EV_MD_OTHER_GATEWAY',
            'resource_type' => 'mandates',
            'action' => 'active',
            'links' => ['mandate' => $token->token],
        ]]);

        $this->assertSame('pending', $token->fresh()->meta->state);
    }

    public function test_mandate_event_recovers_a_missing_token_using_the_client_hash(): void
    {
        $job = $this->dispatchEvents([[
            'id' => 'EV_MD_RECOVER',
            'resource_type' => 'mandates',
            'action' => 'created',
            'resource_metadata' => ['client_hash' => $this->client->client_hash],
            'links' => ['mandate' => 'MD_RECOVER'],
        ]]);

        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gocardless_gateway->id)
            ->where('token', 'MD_RECOVER')
            ->firstOrFail();

        $this->assertSame($this->client->id, $job->synced_client_id);
        $this->assertSame($this->client->id, $token->client_id);
    }

    public function test_mandate_event_without_a_known_token_or_client_hash_is_ignored(): void
    {
        $job = $this->dispatchEvents([[
            'id' => 'EV_MD_UNKNOWN',
            'resource_type' => 'mandates',
            'action' => 'created',
            'links' => ['mandate' => 'MD_UNKNOWN'],
        ]]);

        $this->assertNull($job->synced_client_id);
        $this->assertFalse(ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gocardless_gateway->id)
            ->where('token', 'MD_UNKNOWN')
            ->exists());
    }

    public function test_replaced_mandate_updates_only_the_referenced_payment_method(): void
    {
        $token = $this->makeMandate('MD_REPLACED');
        $hashed_id = $token->hashed_id;
        $other_token = $this->makeMandate('MD_UNRELATED');

        $this->dispatchEvents([[
            'id' => 'EV_MD_REPLACED',
            'resource_type' => 'mandates',
            'action' => 'replaced',
            'links' => [
                'mandate' => 'MD_REPLACED',
                'new_mandate' => 'MD_REPLACEMENT',
            ],
        ]]);

        $this->assertSame($token->id, $token->fresh()->id);
        $this->assertSame($hashed_id, $token->fresh()->hashed_id);
        $this->assertSame('MD_REPLACEMENT', $token->fresh()->token);
        $this->assertSame('MD_UNRELATED', $other_token->fresh()->token);
    }

    public function test_replaced_mandate_event_is_idempotent(): void
    {
        $token = $this->makeMandate('MD_RETRY_OLD');
        $event = [
            'id' => 'EV_MD_RETRY',
            'resource_type' => 'mandates',
            'action' => 'replaced',
            'links' => [
                'mandate' => 'MD_RETRY_OLD',
                'new_mandate' => 'MD_RETRY_NEW',
            ],
        ];

        $this->dispatchEvents([$event]);
        $this->dispatchEvents([$event]);

        $this->assertSame($token->id, ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gocardless_gateway->id)
            ->where('token', 'MD_RETRY_NEW')
            ->firstOrFail()
            ->id);
        $this->assertSame(1, ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gocardless_gateway->id)
            ->where('token', 'MD_RETRY_NEW')
            ->count());
    }

    public function test_replaced_mandate_recovers_the_client_without_altering_other_tokens(): void
    {
        $other_token = $this->makeMandate('MD_EXISTING');

        $this->dispatchEvents([[
            'id' => 'EV_MD_REPLACEMENT_RECOVER',
            'resource_type' => 'mandates',
            'action' => 'replaced',
            'resource_metadata' => ['client_hash' => $this->client->client_hash],
            'links' => [
                'mandate' => 'MD_MISSING',
                'new_mandate' => 'MD_RECOVERED',
            ],
        ]]);

        $this->assertSame($this->client->id, ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gocardless_gateway->id)
            ->where('token', 'MD_RECOVERED')
            ->firstOrFail()
            ->client_id);
        $this->assertSame('MD_EXISTING', $other_token->fresh()->token);
    }

    public function test_replaced_mandate_is_scoped_to_the_current_company_gateway(): void
    {
        $token = $this->makeMandate('MD_OTHER_GATEWAY_REPLACED');
        $other_gateway = $this->gocardless_gateway->replicate();
        $other_gateway->save();
        $token->company_gateway_id = $other_gateway->id;
        $token->saveQuietly();

        $this->dispatchEvents([[
            'id' => 'EV_MD_OTHER_GATEWAY_REPLACED',
            'resource_type' => 'mandates',
            'action' => 'replaced',
            'links' => [
                'mandate' => 'MD_OTHER_GATEWAY_REPLACED',
                'new_mandate' => 'MD_OTHER_GATEWAY_REPLACEMENT',
            ],
        ]]);

        $this->assertSame('MD_OTHER_GATEWAY_REPLACED', $token->fresh()->token);
        $this->assertFalse(ClientGatewayToken::query()
            ->where('company_gateway_id', $this->gocardless_gateway->id)
            ->where('token', 'MD_OTHER_GATEWAY_REPLACEMENT')
            ->exists());
    }

    public function test_fulfilled_billing_request_does_not_recreate_an_existing_payment(): void
    {
        $payment = $this->makePayment('PM_BILLING_FULFILLED', Payment::STATUS_PENDING);
        $payment_hash = PaymentHash::query()->create([
            'hash' => str()->random(32),
            'payment_id' => $payment->id,
            'data' => [
                'client_id' => $this->client->id,
                'gocardless' => [
                    'billing_request' => 'BRQ_ALREADY_COMPLETED',
                    'gateway_type_id' => GatewayType::DIRECT_DEBIT,
                ],
            ],
        ]);

        $this->dispatchEvents([[
            'id' => 'EV_BRQ_ALREADY_COMPLETED',
            'resource_type' => 'billing_requests',
            'action' => 'fulfilled',
            'links' => ['billing_request' => 'BRQ_ALREADY_COMPLETED'],
        ]]);

        $this->assertSame($payment->id, $payment_hash->fresh()->payment_id);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status_id);
    }

    public function test_fulfilled_billing_request_without_a_payment_hash_is_ignored(): void
    {
        $payment_count = Payment::query()->count();

        $this->dispatchEvents([[
            'id' => 'EV_BRQ_UNKNOWN',
            'resource_type' => 'billing_requests',
            'action' => 'fulfilled',
            'links' => ['billing_request' => 'BRQ_UNKNOWN'],
        ]]);

        $this->assertSame($payment_count, Payment::query()->count());
    }

    public function test_irrelevant_event_does_not_abort_remaining_events(): void
    {
        $payment = $this->makePayment('PM_BATCH_1', Payment::STATUS_PENDING);

        $this->dispatchEvents([
            [
                'id' => 'EV_IRRELEVANT',
                'resource_type' => 'customers',
                'action' => 'updated',
                'links' => ['customer' => 'CU_1'],
            ],
            [
                'id' => 'EV_GOOD',
                'resource_type' => 'payments',
                'action' => 'confirmed',
                'links' => ['payment' => 'PM_BATCH_1'],
            ],
        ]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }

    public function test_operational_exception_is_rethrown_for_queue_retry(): void
    {
        $payment = $this->makePayment('PM_RETRY_1', Payment::STATUS_PENDING);

        Payment::saving(function (Payment $saving_payment): void {
            if ($saving_payment->transaction_reference === 'PM_RETRY_1') {
                throw new RuntimeException('Simulated database failure');
            }
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated database failure');

        $this->dispatchEvents([[
            'id' => 'EV_RETRY',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['payment' => $payment->transaction_reference],
        ]]);
    }
}

class TestableGoCardlessWebhook extends GoCardlessWebhook
{
    public ?int $synced_client_id = null;

    /** @var array<string, string> */
    private array $mandate_actions;

    public function __construct(array $events, string $company_key, int $company_gateway_id)
    {
        $this->mandate_actions = collect($events)
            ->where('resource_type', 'mandates')
            ->filter(fn(array $event): bool => isset($event['links']['mandate']))
            ->mapWithKeys(fn(array $event): array => [$event['links']['mandate'] => $event['action']])
            ->all();

        parent::__construct($events, $company_key, $company_gateway_id);
    }

    protected function syncMandate(CompanyGateway $company_gateway, Client $client, string $mandate_id): void
    {
        $this->synced_client_id = $client->id;
        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $company_gateway->id)
            ->where('token', $mandate_id)
            ->first();

        if (! $token) {
            $token = new ClientGatewayToken();
            $token->company_id = $client->company_id;
            $token->client_id = $client->id;
            $token->company_gateway_id = $company_gateway->id;
            $token->gateway_type_id = GatewayType::DIRECT_DEBIT;
            $token->token = $mandate_id;
            $token->gateway_customer_reference = 'CU_WEBHOOK';
        }

        $meta = $token->meta ?? new \stdClass();
        $meta->state = match ($this->mandate_actions[$mandate_id] ?? null) {
            'active' => 'authorized',
            'cancelled', 'expired', 'failed' => $this->mandate_actions[$mandate_id],
            default => $meta->state ?? 'pending',
        };
        $token->meta = $meta;
        $token->save();
    }

    protected function replaceMandate(
        CompanyGateway $company_gateway,
        Client $client,
        string $mandate_id,
        string $new_mandate_id,
    ): void {
        $this->synced_client_id = $client->id;
        $token = ClientGatewayToken::query()
            ->where('company_gateway_id', $company_gateway->id)
            ->whereIn('token', [$mandate_id, $new_mandate_id])
            ->first();

        if (! $token) {
            $this->syncMandate($company_gateway, $client, $new_mandate_id);

            return;
        }

        $token->token = $new_mandate_id;
        $token->save();
    }
}
