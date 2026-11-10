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

use App\DataMapper\FeesAndLimits;
use App\Factory\CompanyGatewayFactory;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceFactory;
use App\Jobs\Cron\ReconcileTerminalPayment;
use App\Jobs\Cron\TerminalReconcileCron;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\TerminalPaymentIntent;
use App\Models\User;
use App\PaymentDrivers\Stripe\Jobs\ChargeRefunded;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentFailureWebhook;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentWebhook;
use App\Repositories\InvoiceRepository;
use App\Services\Client\PaymentMethod;
use App\Services\Invoice\AutoBillInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\MockAccountData;
use Tests\TestCase;

class StripeTerminalTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $company_gateway;

    private TerminalStripeFake $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        Cache::flush();

        config(['ninja.terminal_enabled' => true]);

        $this->company_gateway = $this->stripeGateway();
        $this->invoice = $this->invoice->service()->markSent()->save();

        $this->stripe = new TerminalStripeFake();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    private function stripeGateway(array $card_present = ['is_enabled' => true], ?int $company_id = null, string $key = 'd14dd26a37cecc30fdd65700bfb55b23'): CompanyGateway
    {
        $fees = new FeesAndLimits();

        foreach ($card_present as $field => $value) {
            $fees->{$field} = $value;
        }

        $company_gateway = CompanyGatewayFactory::create($company_id ?? $this->company->id, $this->user->id);
        $company_gateway->gateway_key = $key;
        $company_gateway->accepted_credit_cards = 0;
        $company_gateway->fees_and_limits = (object) [(string) GatewayType::CARD_PRESENT => $fees];
        $company_gateway->setConfig($key == 'd14dd26a47cecc30fdd65700bfb67b34'
            ? ['account_id' => 'acct_terminal_test']
            : ['apiKey' => 'sk_test_terminal', 'publishableKey' => 'pk_test_terminal']);
        $company_gateway->save();

        return $company_gateway;
    }

    /* The schema dump skips the data migration that adds Stripe Connect */
    private function stripeConnectGateway(): Gateway
    {
        $gateway = Gateway::find(56);

        if (!$gateway) {
            $gateway = new Gateway();
            $gateway->forceFill([
                'id' => 56,
                'name' => 'Stripe Connect',
                'provider' => 'StripeConnect',
                'sort_order' => 1,
                'key' => 'd14dd26a47cecc30fdd65700bfb67b34',
                'fields' => '{"account_id":"","appleDomainVerification":""}',
            ])->save();
        }

        return $gateway;
    }

    private function companyState(string $state): void
    {
        $settings = $this->company->settings;
        $settings->state = $state;
        $this->company->settings = $settings;
        $this->company->save();
    }

    private function surcharged(): array
    {
        $this->companyState('NY');
        $this->company_gateway = $this->stripeGateway(['is_enabled' => true, 'fee_percent' => 3]);
        $this->company_gateway->driver($this->client)->cardPresent()->acknowledgeSurcharge($this->user);

        return $this->quote(true);
    }

    private function quote(bool $apply_fee): array
    {
        return $this->company_gateway->fresh()->driver($this->client)->cardPresent()->surcharge()->quote($this->invoice->fresh(), (float) $this->invoice->balance, $apply_fee);
    }

    private function api(?string $token = null): self
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token ?? $this->token,
        ]);
    }

    private function collect(array $overrides = [], ?Invoice $invoice = null): TestResponse
    {
        $invoice ??= $this->invoice;

        return $this->api()->postJson("/api/v1/invoices/{$invoice->hashed_id}/terminal/payment_intents", array_merge([
            'company_gateway_id' => $this->company_gateway->hashed_id,
            'amount' => (float) $invoice->balance,
            'apply_fee' => false,
            'collection_id' => (string) Str::uuid(),
            'expected_total' => (float) $invoice->balance,
        ], $overrides));
    }

    private function finalize(string $payment_intent): TestResponse
    {
        return $this->api()->postJson("/api/v1/terminal/payment_intents/{$payment_intent}/finalize");
    }

    private function complete(string $payment_intent, ?string $token = null): TestResponse
    {
        return $this->api($token)->postJson("/api/v1/terminal/payment_intents/{$payment_intent}/complete");
    }

    private function terminalPayments()
    {
        return Payment::query()->where('company_id', $this->company->id)->where('gateway_type_id', GatewayType::CARD_PRESENT);
    }

    private function refunds()
    {
        return collect($this->stripe->requests)->where('path', '/v1/refunds')->where('method', 'post');
    }

    private function userToken(string $permissions): string
    {
        $user = User::factory()->create(['account_id' => $this->account->id, 'email' => Str::uuid() . '@example.test']);

        $company_user = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $company_user->is_owner = false;
        $company_user->is_admin = false;
        $company_user->is_locked = false;
        $company_user->permissions = $permissions;
        $company_user->save();

        $token = new CompanyToken();
        $token->user_id = $user->id;
        $token->company_id = $this->company->id;
        $token->account_id = $this->account->id;
        $token->name = 'terminal';
        $token->token = Str::random(64);
        $token->is_system = true;
        $token->save();

        return $token->token;
    }

    public function testThePortalNeverOffersCardPresent(): void
    {
        $this->assertArrayHasKey(GatewayType::CARD_PRESENT, Gateway::find(20)->getMethods());
        $this->assertArrayHasKey(GatewayType::CARD_PRESENT, $this->stripeConnectGateway()->getMethods());

        foreach ((new PaymentMethod($this->client, 100))->run() as $method) {
            $this->assertNotEquals(GatewayType::CARD_PRESENT, (int) ($method['gateway_type_id'] ?? 0));
        }
    }

    public function testACollectedCardIsRecordedOnTheInvoice(): void
    {
        $amount = (float) $this->invoice->balance;

        $intent = $this->collect()->assertOk()->json();

        $this->assertSame(['card_present'], $this->stripe->intents[$intent['payment_intent_id']]['payment_method_types']);
        $this->assertSame((int) round($amount * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);

        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);

        $payment = $this->terminalPayments()->sole();

        $this->assertEquals($amount, $payment->amount);
        $this->assertSame(PaymentType::VISA, (int) $payment->type_id);
        $this->assertStringStartsWith('ch_', $payment->transaction_reference);
        $this->assertSame(GatewayType::CARD_PRESENT, $payment->meta->type);
        $this->assertSame('4242', $payment->meta->last4);
        $this->assertSame(Invoice::STATUS_PAID, $this->invoice->fresh()->status_id);
    }

    public function testCompleteTheWebhookAndReconciliationRecordOnce(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        $this->complete($intent['payment_intent_id'])->assertOk();
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
        (new PaymentIntentWebhook(['object' => $this->stripe->intents[$intent['payment_intent_id']]], $this->company->company_key, $this->company_gateway->id))->handle();
        (new TerminalReconcileCron())->handle();

        $this->assertSame(1, $this->terminalPayments()->count());
    }

    public function testEveryAnswerForARecordedPaymentCarriesTheReceipt(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        (new PaymentIntentWebhook(['object' => $this->stripe->intents[$intent['payment_intent_id']]], $this->company->company_key, $this->company_gateway->id))->handle();

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded', 'receipt_url' => 'https://pay.stripe.com/receipts/test']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['receipt_url' => 'https://pay.stripe.com/receipts/test']);
    }

    public function testTheWebhookRecordsWithoutTheDevice(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        (new PaymentIntentWebhook(['object' => ['id' => $intent['payment_intent_id'], 'payment_method_types' => ['card_present']]], $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertSame(1, $this->terminalPayments()->count());
    }

    public function testReconciliationRecordsAChargeNobodyCompleted(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        (new TerminalReconcileCron())->handle();

        $this->assertSame(1, $this->terminalPayments()->count());
        $this->assertTrue(SystemLog::query()->where('company_id', $this->company->id)->where('event_id', SystemLog::EVENT_PAYMENT_RECONCILIATION_SUCCESS)->exists());
    }

    public function testAChargeReconciliationCannotRecordIsAlertedOnce(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);
        $collection = TerminalPaymentIntent::query()->where('payment_intent_id', $intent['payment_intent_id'])->sole();

        /* Recording fails for a reason that is not Stripe's, every run */
        ClientContact::query()->where('client_id', $this->client->id)->delete();

        /* Another database's collection with the same id was alerted already */
        Cache::put("terminal_reconcile_failed:{$collection->id}", true);

        foreach (range(1, 2) as $run) {
            try {
                (new ReconcileTerminalPayment($collection->id, null))->handle();
                $this->fail('recording should have failed');
            } catch (\Throwable $e) {
                $this->assertStringNotContainsString('recording should have failed', $e->getMessage());
            }
        }

        $this->assertSame(0, $this->terminalPayments()->count());
        $this->assertSame(1, SystemLog::query()->where('company_id', $this->company->id)->where('event_id', SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE)->count());
    }

    public function testAPaymentIntentInFlightIsNeverMarkedCancelled(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->intents[$intent['payment_intent_id']]['status'] = 'processing';

        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/cancel")->assertOk()->assertJson(['status' => 'processing']);

        TerminalPaymentIntent::query()->update(['touched_at' => now()->subMinutes(30)]);
        (new TerminalReconcileCron())->handle();

        $this->assertSame(TerminalPaymentIntent::STATUS_OPEN, TerminalPaymentIntent::query()->where('payment_intent_id', $intent['payment_intent_id'])->sole()->status_id);

        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
    }

    public function testTheSameCollectionReturnsTheSamePaymentIntent(): void
    {
        $collection_id = (string) Str::uuid();

        $first = $this->collect(['collection_id' => $collection_id])->assertOk()->json();
        $second = $this->collect(['collection_id' => $collection_id])->assertOk()->json();

        $this->assertSame($first['payment_intent_id'], $second['payment_intent_id']);

        $create = collect($this->stripe->requests)->first(fn ($request) => $request['method'] == 'post' && $request['path'] == '/v1/payment_intents');
        $this->assertSame("in_terminal_{$this->company->id}_{$this->invoice->id}_{$collection_id}", $create['headers']['Idempotency-Key']);
    }

    public function testStripeConnectChargesTheConnectedAccount(): void
    {
        config(['ninja.ninja_stripe_key' => 'sk_test_platform']);
        $this->stripeConnectGateway();
        $this->company_gateway = $this->stripeGateway(key: 'd14dd26a47cecc30fdd65700bfb67b34');

        $this->collect()->assertOk();

        $create = collect($this->stripe->requests)->first(fn ($request) => $request['method'] == 'post' && $request['path'] == '/v1/payment_intents');
        $this->assertSame('acct_terminal_test', $create['headers']['Stripe-Account']);
    }

    public function testOneOpenCollectionPerInvoice(): void
    {
        $this->collect()->assertOk();

        $this->collect()->assertStatus(409)->assertJson(['error_code' => 'collection_open']);

        TerminalPaymentIntent::query()->update(['touched_at' => now()->subMinutes(21), 'created_at' => now()->subMinutes(21)]);

        $this->collect()->assertOk();
        $this->assertSame(1, TerminalPaymentIntent::query()->where('status_id', TerminalPaymentIntent::STATUS_CANCELLED)->count());
    }

    public function testAChargedCollectionIsRecordedInsteadOfStartingAnother(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        $this->collect()->assertStatus(409)->assertJson(['error_code' => 'already_paid']);

        $this->assertSame(1, $this->terminalPayments()->count());
    }

    public function testACancelledCollectionStaysClosed(): void
    {
        $collection_id = (string) Str::uuid();
        $intent = $this->collect(['collection_id' => $collection_id])->json();

        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/cancel")->assertOk()->assertJson(['status' => 'canceled']);

        $this->collect(['collection_id' => $collection_id])->assertStatus(409)->assertJson(['error_code' => 'collection_closed']);
    }

    public function testADraftIsValidatedAgainstItsAmountAndMarkedSent(): void
    {
        $draft = InvoiceFactory::create($this->company->id, $this->user->id);
        $draft->client_id = $this->client->id;
        $draft->line_items = $this->buildLineItems();
        $draft->save();
        $draft = $draft->calc()->getInvoice();
        $draft->save();

        $this->assertEquals(0, $draft->balance);

        $this->collect(['amount' => (float) $draft->amount, 'expected_total' => (float) $draft->amount], $draft)->assertOk();

        $this->assertSame(Invoice::STATUS_SENT, $draft->fresh()->status_id);
    }

    public function testADraftThatLocksOnSendStillTakesItsSurcharge(): void
    {
        $this->surcharged();

        $settings = $this->company->settings;
        $settings->lock_invoices = 'when_sent';
        $this->company->settings = $settings;
        $this->company->save();

        $client_settings = $this->client->settings;
        $client_settings->lock_invoices = 'when_sent';
        $this->client->settings = $client_settings;
        $this->client->save();

        $draft = InvoiceFactory::create($this->company->id, $this->user->id);
        $draft->client_id = $this->client->id;
        $draft->line_items = $this->buildLineItems();
        $draft->save();
        $draft = $draft->calc()->getInvoice();
        $draft->save();

        $quote = $this->company_gateway->fresh()->driver($this->client)->cardPresent()->surcharge()->quote($draft, (float) $draft->amount, true);
        $this->assertTrue($quote['surcharge_allowed']);

        $intent = $this->collect(['amount' => (float) $draft->amount, 'apply_fee' => true, 'expected_total' => $quote['total']], $draft)->assertOk()->json();

        /* Creating it sent the draft, which locked it: the surcharge it was shown still stands */
        $this->assertTrue($draft->fresh()->isLocked());
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'recollect', 'recollect' => true]);
        $this->assertSame((int) round($quote['total'] * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);
    }

    public function testACreditCardOnTheSecondReadStillChecksTheInvoice(): void
    {
        $quote = $this->surcharged();

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);

        $this->invoice->fresh()->forceFill(['status_id' => Invoice::STATUS_CANCELLED])->saveQuietly();

        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertStatus(409)->assertJson(['error_code' => 'balance_changed']);
    }

    public function testAConnectionTokenNamesOneOfTheGatewaysLocations(): void
    {
        $url = "/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/connection_token";

        $this->api()->postJson($url)->assertStatus(422);
        $this->api()->postJson($url, ['location_id' => 'tml_someone_elses'])->assertStatus(422)->assertJson(['error_code' => 'location_required']);
        $this->api()->postJson($url, ['location_id' => 'tml_1'])->assertOk()->assertJsonStructure(['secret']);
    }

    public function testACreateStripeNeverAnsweredFreesTheInvoiceAfterTwoMinutes(): void
    {
        $this->collect()->assertOk();
        $stuck = TerminalPaymentIntent::query()->where('invoice_id', $this->invoice->id)->sole();
        $stuck->forceFill(['status_id' => TerminalPaymentIntent::STATUS_CREATING, 'payment_intent_id' => null, 'created_at' => now()->subMinutes(3)])->save();

        $this->collect()->assertOk();

        $this->assertSame(TerminalPaymentIntent::STATUS_FAILED, $stuck->fresh()->status_id);
    }

    public function testAnAmountOverTheBalanceIsRefused(): void
    {
        $this->collect(['amount' => (float) $this->invoice->balance + 1, 'expected_total' => (float) $this->invoice->balance + 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function testTheTerminalLimitsAreTheirOwn(): void
    {
        /* Twenty reports this minute: an unprefixed limit counts by the user alone */
        foreach (range(1, 20) as $attempt) {
            RateLimiter::hit(sha1((string) $this->user->id));
        }

        $this->api()->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/connection_token", ['location_id' => 'tml_1'])->assertOk();
        $this->collect()->assertOk();
    }

    public function testAnotherCompanysRecordsAreOutOfReach(): void
    {
        $other_company = Company::factory()->create(['account_id' => $this->account->id]);
        $other_client = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $other_company->id]);
        $other_invoice = InvoiceFactory::create($other_company->id, $this->user->id);
        $other_invoice->client_id = $other_client->id;
        $other_invoice->line_items = $this->buildLineItems();
        $other_invoice->save();
        $other_invoice = $other_invoice->calc()->getInvoice();
        $other_invoice = $other_invoice->service()->markSent()->save();
        $other_gateway = $this->stripeGateway(company_id: $other_company->id);

        $this->collect([], $other_invoice)->assertStatus(401);
        $this->collect(['company_gateway_id' => $other_gateway->hashed_id])->assertStatus(422);
        $this->api()->postJson("/api/v1/company_gateways/{$other_gateway->hashed_id}/terminal/connection_token")->assertStatus(401);

        TerminalPaymentIntent::query()->create([
            'company_id' => $other_company->id, 'user_id' => $this->user->id, 'invoice_id' => $other_invoice->id,
            'company_gateway_id' => $other_gateway->id, 'collection_id' => (string) Str::uuid(), 'payment_intent_id' => 'pi_other_company',
            'currency' => 'usd', 'amount' => 10, 'status_id' => TerminalPaymentIntent::STATUS_OPEN,
        ]);

        $this->complete('pi_other_company')->assertStatus(400);
    }

    public function testPermissions(): void
    {
        $viewer = $this->userToken('["view_invoice"]');

        $this->api($viewer)->getJson('/api/v1/terminal/config')->assertStatus(401);
        $this->api($viewer)->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/connection_token")->assertStatus(401);

        $collector = $this->userToken('["create_payment","view_invoice"]');

        $config = $this->api($collector)->getJson('/api/v1/terminal/config')->assertOk()->json();
        $this->assertSame($this->company_gateway->hashed_id, $config['gateways'][0]['company_gateway_id']);
        $this->api($collector)->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/locations")->assertStatus(401);
        $this->api($collector)->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/surcharge_acknowledgement")->assertStatus(401);
    }

    public function testCardPresentSwitchedOffOnTheGatewayIsNotAnAuthFailure(): void
    {
        $this->company_gateway->fees_and_limits = (object) [];
        $this->company_gateway->save();

        $this->api()->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/connection_token", ['location_id' => 'tml_1'])
            ->assertStatus(403)->assertJson(['error_code' => 'not_enabled']);
        $this->api()->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/locations")
            ->assertStatus(403)->assertJson(['error_code' => 'not_enabled']);

        $viewer = $this->userToken('["view_invoice"]');
        $this->api($viewer)->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/connection_token", ['location_id' => 'tml_1'])->assertStatus(401);
    }

    public function testFinishingIgnoresThePlanAndTheKillSwitch(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        $this->account->plan = 'free';
        $this->account->save();
        config(['ninja.terminal_enabled' => false, 'ninja.production' => true]);

        $this->collect()->assertStatus(403)->assertJson(['error_code' => 'disabled']);
        config(['ninja.terminal_enabled' => true]);
        $this->collect()->assertStatus(403)->assertJson(['error_code' => 'plan_required']);
        config(['ninja.terminal_enabled' => false]);

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
    }

    public function testADeletedInvoiceGetsAnUnappliedPayment(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        $this->company_gateway->fees_and_limits = (object) [];
        $this->company_gateway->save();
        (new InvoiceRepository())->delete($this->invoice->fresh());

        $balance = (float) $this->client->fresh()->balance;
        $paid_to_date = (float) $this->client->fresh()->paid_to_date;

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'unapplied']);

        $payment = $this->terminalPayments()->sole();
        $this->assertEquals(0, $payment->applied);
        $this->assertTrue($this->invoice->fresh()->is_deleted);
        $this->assertEquals($paid_to_date + $payment->amount, (float) $this->client->fresh()->paid_to_date);
        $this->assertEquals($balance, (float) $this->client->fresh()->balance);
    }

    public function testAnArchivedInvoiceIsStillPaid(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        $this->invoice->delete();

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);

        $this->assertEquals(0, (float) Invoice::withTrashed()->find($this->invoice->id)->balance);
    }

    public function testMoneyBeyondTheInvoiceStillCountsAsPaid(): void
    {
        $balance = (float) $this->invoice->balance;
        $paid_to_date = (float) $this->client->fresh()->paid_to_date;

        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id'], received: (int) round(($balance + 5) * 100));

        $this->complete($intent['payment_intent_id'])->assertOk();

        $this->assertEquals($paid_to_date + $balance + 5, (float) $this->client->fresh()->paid_to_date);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
    }

    public function testAnInvoicePartlyPaidMeanwhileTakesOnlyItsBalance(): void
    {
        $balance = (float) $this->invoice->balance;
        $paid_to_date = (float) $this->client->fresh()->paid_to_date;

        $intent = $this->collect()->json();

        /* Another payment took all but 4 of it while the card was being read */
        $this->invoice->fresh()->forceFill(['balance' => 4])->saveQuietly();

        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk();

        $payment = $this->terminalPayments()->sole();
        $this->assertEquals($balance, (float) $payment->amount);
        $this->assertEquals(4, (float) $payment->applied);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertEquals($paid_to_date + $balance, (float) $this->client->fresh()->paid_to_date);
    }

    public function testAChargeIsCountedInTheCurrencyItWasTakenIn(): void
    {
        $balance = (float) $this->invoice->balance;
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        /* The client moved to a zero-decimal currency while the card was read */
        $settings = $this->client->settings;
        $settings->currency_id = '45';
        $this->client->settings = $settings;
        $this->client->save();

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);

        $this->assertEquals($balance, (float) $this->terminalPayments()->sole()->amount);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
    }

    public function testACollectionIsNotChargedOnceTheClientsCurrencyChanged(): void
    {
        $quote = $this->surcharged();
        $base = (int) round((float) $this->invoice->balance * 100);

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');

        /* The client moved to a zero-decimal currency while the card was read */
        $settings = $this->client->settings;
        $settings->currency_id = '45';
        $this->client->settings = $settings;
        $this->client->save();

        $this->finalize($intent['payment_intent_id'])->assertStatus(422)->assertJson(['error_code' => 'unsupported_currency']);
        $this->assertSame($base, $this->stripe->intents[$intent['payment_intent_id']]['amount']);
    }

    public function testFinalizeStillChecksTheInvoiceWithoutASurcharge(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->collect($intent['payment_intent_id'], 'debit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['recollect' => false]);

        $this->invoice->fresh()->forceFill(['status_id' => Invoice::STATUS_CANCELLED])->saveQuietly();

        $this->finalize($intent['payment_intent_id'])->assertStatus(409)->assertJson(['error_code' => 'balance_changed']);
    }

    public function testASurchargeIsDecidedAgainForTheNextCard(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'debit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['path' => 'none', 'recollect' => false]);

        /* That card was declined; the customer presents a credit card */
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'recollect', 'recollect' => true]);
        $this->assertSame((int) round($quote['total'] * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);
        $this->assertGreaterThan((int) round($balance * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);
    }

    public function testAPaymentIntentTheDeviceCreatedIsRejected(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);
        $this->stripe->intents[$intent['payment_intent_id']]['metadata']['payment_hash'] = 'not-ours';

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'rejected']);

        $this->assertSame(0, $this->terminalPayments()->count());
    }

    public function testAnUnderCollectedChargeAppliesOnlyWhatWasReceived(): void
    {
        $balance = (float) $this->invoice->balance;
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id'], received: (int) round(($balance - 5) * 100));

        $this->complete($intent['payment_intent_id'])->assertOk();

        $this->assertEquals(5, (float) $this->invoice->fresh()->balance);
    }

    public function testACreditCardTakesTheSurchargeOnASecondTap(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;

        $this->api()->getJson("/api/v1/invoices/{$this->invoice->hashed_id}/terminal/quote?company_gateway_id={$this->company_gateway->hashed_id}&amount={$balance}&apply_fee=1")
            ->assertOk()->assertJson(['surcharge_allowed' => true, 'total' => $quote['total']]);

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->assertOk()->json();
        $this->assertSame((int) round($balance * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);

        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'recollect', 'recollect' => true]);
        $this->assertSame((int) round($quote['total'] * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);

        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'recollect', 'recollect' => false]);

        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);

        $fee_lines = collect($this->invoice->fresh()->line_items)->where('type_id', '4');
        $this->assertCount(1, $fee_lines);
        $this->assertSame(ctrans('texts.credit_card_surcharge'), $fee_lines->first()->notes);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertCount(0, $this->refunds());
    }

    public function testADebitCardOnTheSecondTapDropsTheSurcharge(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);

        $this->stripe->collect($intent['payment_intent_id'], 'debit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'none', 'recollect' => true]);

        $this->assertSame((int) round($balance * 100), $this->stripe->intents[$intent['payment_intent_id']]['amount']);
    }

    public function testAPaymentIntentLeftAtAnotherAmountIsSetBackAndReadAgain(): void
    {
        $quote = $this->surcharged();
        $base = (int) round((float) $this->invoice->balance * 100);
        $total = (int) round($quote['total'] * 100);

        /* Raised for a credit card, but the response was lost before the surcharge was saved */
        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->intents[$intent['payment_intent_id']]['amount'] = $total;

        $this->stripe->collect($intent['payment_intent_id'], 'debit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'none', 'recollect' => true, 'amount_minor' => $base]);
        $this->assertSame($base, $this->stripe->intents[$intent['payment_intent_id']]['amount']);

        $this->stripe->collect($intent['payment_intent_id'], 'debit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['recollect' => false, 'amount_minor' => $base]);

        /* Raised, then lowered for a debit card whose response was lost */
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['path' => 'recollect', 'recollect' => true]);
        $this->stripe->intents[$intent['payment_intent_id']]['amount'] = $base;

        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'recollect', 'recollect' => true, 'amount_minor' => $total]);
        $this->assertSame($total, $this->stripe->intents[$intent['payment_intent_id']]['amount']);

        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['recollect' => false, 'amount_minor' => $total]);
    }

    public function testASurchargeChargedWithoutItsPathIsRefunded(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;

        /* Raised for a credit card, the surcharge never saved, and the card charged anyway */
        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->intents[$intent['payment_intent_id']]['amount'] = (int) round($quote['total'] * 100);
        $this->stripe->charge($intent['payment_intent_id'], funding: 'credit');

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);

        $this->assertCount(1, $this->refunds());
        $this->assertSame((int) round($quote['fee_gross'] * 100), (int) $this->refunds()->first()['params']['amount']);
        $this->assertEquals($balance, (float) $this->terminalPayments()->sole()->amount);
        $this->assertCount(0, collect($this->invoice->fresh()->line_items)->where('type_id', '4'));
    }

    public function testADebitCardIsNeverSurcharged(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'debit');
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertJson(['path' => 'none', 'recollect' => false]);

        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk();

        $this->assertCount(0, collect($this->invoice->fresh()->line_items)->where('type_id', '4'));
        $this->assertEquals($balance, $this->terminalPayments()->sole()->amount);
    }

    public function testASurchargeChargedToADebitCardIsRefundedOnceAndNotCountedTwice(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);

        /* The second read was skipped and a debit card charged the raised amount */
        $this->stripe->charge($intent['payment_intent_id'], funding: 'debit');

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
        $this->complete($intent['payment_intent_id'])->assertOk();
        (new TerminalReconcileCron())->handle();

        $this->assertCount(1, $this->refunds());
        $this->assertSame((int) round($quote['fee_gross'] * 100), (int) $this->refunds()->first()['params']['amount']);
        $this->assertSame("in_terminal_surcharge_{$intent['payment_intent_id']}", $this->refunds()->first()['headers']['Idempotency-Key']);
        $this->assertEquals($balance, $this->terminalPayments()->sole()->amount);

        $charge = $this->stripe->intents[$intent['payment_intent_id']]['latest_charge'];
        (new ChargeRefunded(['object' => $charge], $this->company->company_key))->handle();

        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertEquals(0, (float) $this->terminalPayments()->sole()->refunded);
        $this->assertCount(0, collect($this->invoice->fresh()->line_items)->where('type_id', '4'));
    }

    public function testARefundIsCountedOnceWhicheverArrivesFirst(): void
    {
        $balance = (float) $this->invoice->balance;
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        /* Refunded in Stripe before the payment was recorded: the webhook finds no payment */
        $this->stripe->refundInDashboard($intent['payment_intent_id'], 500);
        $refunded = fn () => new ChargeRefunded(['object' => $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']], $this->company->company_key);
        $refunded()->handle();

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
        $refunded()->handle();

        $payment = $this->terminalPayments()->sole();
        $this->assertEquals($balance, $payment->amount);
        $this->assertEquals(5, (float) $payment->refunded);
        $this->assertEquals(5, (float) $this->invoice->fresh()->balance);
    }

    public function testARefundOfAPaymentNoInvoiceTookComesOutOfTheClientsMoney(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);
        (new InvoiceRepository())->delete($this->invoice->fresh());

        $paid_to_date = (float) $this->client->fresh()->paid_to_date;
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'unapplied']);
        $amount = (float) $this->terminalPayments()->sole()->amount;

        $refunded = fn () => new ChargeRefunded(['object' => $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']], $this->company->company_key);

        $this->stripe->refundInDashboard($intent['payment_intent_id'], 300);
        $refunded()->handle();

        $this->assertEquals(3, (float) $this->terminalPayments()->sole()->refunded);
        $this->assertEquals($paid_to_date + $amount - 3, (float) $this->client->fresh()->paid_to_date);

        $this->stripe->refundInDashboard($intent['payment_intent_id'], (int) round($amount * 100) - 300);
        $refunded()->handle();

        $payment = $this->terminalPayments()->sole();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status_id);
        $this->assertEquals($amount, (float) $payment->refunded);
        $this->assertEquals($paid_to_date, (float) $this->client->fresh()->paid_to_date);
        $this->assertTrue($this->invoice->fresh()->is_deleted);
    }

    public function testARefundComesOutOfTheMoneyTheInvoiceDidNotTakeFirst(): void
    {
        $balance = (float) $this->invoice->balance;
        $paid_to_date = (float) $this->client->fresh()->paid_to_date;

        $intent = $this->collect()->json();
        $this->invoice->fresh()->forceFill(['balance' => 4])->saveQuietly();
        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'unapplied']);

        $refunded = fn () => new ChargeRefunded(['object' => $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']], $this->company->company_key);

        /* Within the unapplied part: the invoice stays paid */
        $this->stripe->refundInDashboard($intent['payment_intent_id'], 100);
        $refunded()->handle();

        $this->assertEquals(1, (float) $this->terminalPayments()->sole()->refunded);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertEquals($paid_to_date + $balance - 1, (float) $this->client->fresh()->paid_to_date);
        $this->assertTrue(Activity::query()->where('payment_id', $this->terminalPayments()->sole()->id)->where('activity_type_id', Activity::REFUNDED_PAYMENT)->exists());

        /* Past it: the rest of the unapplied part, then 2 off the invoice */
        $this->stripe->refundInDashboard($intent['payment_intent_id'], (int) round(($balance - 3) * 100));
        $refunded()->handle();

        $this->assertEquals($balance - 2, (float) $this->terminalPayments()->sole()->refunded);
        $this->assertEquals(2, (float) $this->invoice->fresh()->balance);
        $this->assertEquals($paid_to_date + 2, (float) $this->client->fresh()->paid_to_date);
    }

    public function testASurchargeRefundWhoseResponseWasLostIsNotRefundedOrCountedAgain(): void
    {
        $quote = $this->surcharged();

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);
        $this->stripe->charge($intent['payment_intent_id'], funding: 'debit');

        $this->stripe->lose_refund_response = true;
        $this->complete($intent['payment_intent_id'])->assertStatus(500);
        $this->assertSame(0, $this->terminalPayments()->count());

        $refunded = fn () => new ChargeRefunded(['object' => $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']], $this->company->company_key);
        $refunded()->handle();

        (new TerminalReconcileCron())->handle();
        $refunded()->handle();

        $this->assertCount(1, $this->refunds());
        $this->assertEquals(0, (float) $this->terminalPayments()->sole()->refunded);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertSame(Invoice::STATUS_PAID, $this->invoice->fresh()->status_id);
    }

    public function testASurchargeRefundStripeAnsweredWithAServerErrorIsFoundOnTheNextAttempt(): void
    {
        $quote = $this->surcharged();

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);
        $this->stripe->charge($intent['payment_intent_id'], funding: 'debit');

        $this->stripe->refuse_refund = 500;
        $this->complete($intent['payment_intent_id'])->assertStatus(500);
        $this->assertSame(0, $this->terminalPayments()->count());
        $this->assertSame(TerminalPaymentIntent::STATUS_OPEN, TerminalPaymentIntent::query()->where('payment_intent_id', $intent['payment_intent_id'])->value('status_id'));

        $refunded = fn () => new ChargeRefunded(['object' => $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']], $this->company->company_key);
        $refunded()->handle();

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
        $refunded()->handle();

        $this->assertCount(1, $this->refunds());
        $this->assertEquals(0, (float) $this->terminalPayments()->sole()->refunded);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertSame(Invoice::STATUS_PAID, $this->invoice->fresh()->status_id);
    }

    public function testASurchargeStripeWillNotRefundIsRecordedAsTheClientsMoney(): void
    {
        $quote = $this->surcharged();
        $balance = (float) $this->invoice->balance;
        $paid_to_date = (float) $this->client->fresh()->paid_to_date;

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);
        $this->stripe->charge($intent['payment_intent_id'], funding: 'debit');

        $this->stripe->refuse_refund = 400;
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'unapplied', 'unapplied' => $quote['fee_gross']]);

        $payment = $this->terminalPayments()->sole();
        $this->assertEquals($quote['total'], (float) $payment->amount);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertCount(0, collect($this->invoice->fresh()->line_items)->where('type_id', '4'));
        $this->assertEquals($paid_to_date + $quote['total'], (float) $this->client->fresh()->paid_to_date);
        $this->assertSame(1, SystemLog::query()->where('company_id', $this->company->id)->where('event_id', SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE)->count());

        /* Refunded in Stripe by hand: it comes out of that money, not the invoice */
        $this->stripe->refuse_refund = null;
        $this->stripe->refundInDashboard($intent['payment_intent_id'], (int) round($quote['fee_gross'] * 100));
        (new ChargeRefunded(['object' => $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']], $this->company->company_key))->handle();

        $this->assertEquals($quote['fee_gross'], (float) $this->terminalPayments()->sole()->refunded);
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertEquals($paid_to_date + $balance, (float) $this->client->fresh()->paid_to_date);
    }

    public function testASurchargeChargedToAnInteracCardIsTheClientsMoney(): void
    {
        $quote = $this->surcharged();
        $paid_to_date = (float) $this->client->fresh()->paid_to_date;

        $intent = $this->collect(['apply_fee' => true, 'expected_total' => $quote['total']])->json();
        $this->stripe->collect($intent['payment_intent_id'], 'credit');
        $this->finalize($intent['payment_intent_id'])->assertJson(['recollect' => true]);
        $this->stripe->charge($intent['payment_intent_id'], funding: 'credit', method: 'interac_present');

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'unapplied', 'unapplied' => $quote['fee_gross']]);

        $this->assertCount(0, $this->refunds());
        $this->assertCount(0, collect($this->invoice->fresh()->line_items)->where('type_id', '4'));
        $this->assertEquals(0, (float) $this->invoice->fresh()->balance);
        $this->assertEquals($paid_to_date + $quote['total'], (float) $this->client->fresh()->paid_to_date);
    }

    public function testTheSurchargePolicy(): void
    {
        $this->company_gateway = $this->stripeGateway(['is_enabled' => true, 'fee_percent' => 3]);
        $this->companyState('NY');

        $this->assertSame('not_acknowledged', $this->quote(true)['reason']);

        $this->api()->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/surcharge_acknowledgement")->assertOk();
        $this->assertTrue($this->quote(true)['surcharge_allowed']);

        foreach (['Connecticut' => 'region', 'ct' => 'region', 'Colorado' => 'over_cap', 'Narnia' => 'region', '' => 'region'] as $state => $reason) {
            $this->companyState($state);
            $this->assertSame($reason, $this->quote(true)['reason'], $state);
        }

        $this->stripe->account['country'] = 'CA';
        $this->stripe->account['default_currency'] = 'cad';
        Cache::flush();

        $this->companyState('Québec');
        $this->assertSame('region', $this->quote(true)['reason']);

        $this->stripe->account['country'] = 'GB';
        Cache::flush();
        $this->assertSame('region', $this->quote(true)['reason']);
    }

    public function testACurrencyTheAccountDoesNotUseIsRefused(): void
    {
        $this->stripe->account['default_currency'] = 'cad';

        $this->collect()->assertStatus(422)->assertJson(['error_code' => 'unsupported_currency']);
    }

    public function testAutoBillSkipsAnInvoiceBeingCollected(): void
    {
        $this->collect()->assertOk();

        (new AutoBillInvoice($this->invoice->fresh(), $this->company->db))->run();

        $this->assertEquals(0, (int) $this->invoice->fresh()->auto_bill_tries);
    }

    public function testNothingLogsOrQueuesCardholderData(): void
    {
        $intent = $this->collect()->json();
        $object = $this->stripe->intents[$intent['payment_intent_id']];
        $charge = ['id' => 'ch_failed', 'object' => 'charge', 'payment_intent' => $intent['payment_intent_id'], 'payment_method_details' => ['type' => 'card_present', 'card_present' => ['cardholder_name' => 'SECRET NAME']]];

        (new PaymentIntentFailureWebhook(['object' => $object], $this->company->company_key, $this->company_gateway->id))->handle();
        (new PaymentIntentFailureWebhook(['object' => $charge], $this->company->company_key, $this->company_gateway->id))->handle();
        (new PaymentIntentWebhook(['object' => $object], $this->company->company_key, $this->company_gateway->id))->handle();

        foreach (SystemLog::query()->where('company_id', $this->company->id)->get() as $log) {
            $this->assertStringNotContainsString('_secret_', json_encode($log->log));
            $this->assertStringNotContainsString('SECRET NAME', json_encode($log->log));
        }

        $webhook_data = new \ReflectionMethod($this->company_gateway->driver(), 'webhookData');

        foreach ([$object, $charge] as $payload) {
            $queued = json_encode($webhook_data->invoke($this->company_gateway->driver(), ['object' => $payload]));
            $this->assertStringNotContainsString('_secret_', $queued);
            $this->assertStringNotContainsString('SECRET NAME', $queued);
        }

        $this->assertSame(0, $this->terminalPayments()->count());
    }

    public function testLocationsAndReceipts(): void
    {
        $this->companyState('NY');
        $settings = $this->company->settings;
        $settings->address1 = '';
        $settings->country_id = '840';
        $this->company->settings = $settings;
        $this->company->save();

        $this->api()->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/locations")
            ->assertStatus(422)->assertJson(['error_code' => 'location_address_required']);

        $settings = $this->company->settings;
        $settings->address1 = '1 Main St';
        $settings->city = 'New York';
        $settings->state = 'NY';
        $settings->postal_code = '10001';
        $settings->country_id = '840';
        $this->company->settings = $settings;
        $this->company->save();

        $this->api()->postJson("/api/v1/company_gateways/{$this->company_gateway->hashed_id}/terminal/locations")->assertOk();

        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/receipt", ['email' => 'client@example.com'])
            ->assertStatus(409)->assertJson(['error_code' => 'collection_closed']);

        $this->complete($intent['payment_intent_id'])->assertOk();

        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/receipt", ['email' => 'client@example.com'])->assertOk();

        $update = collect($this->stripe->requests)->first(fn ($request) => str_starts_with($request['path'], '/v1/charges/') && $request['method'] == 'post');
        $this->assertSame('client@example.com', $update['params']['receipt_email']);
        /* Stripe sends these outside the account's email limits: three per payment */
        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/receipt", ['email' => 'b@example.com'])->assertOk();
        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/receipt", ['email' => 'c@example.com'])->assertOk();
        $this->api()->postJson("/api/v1/terminal/payment_intents/{$intent['payment_intent_id']}/receipt", ['email' => 'd@example.com'])
            ->assertStatus(429)->assertJson(['error_code' => 'receipt_limit']);
    }

    public function testAnInteracPaymentIsNotRefundedThroughTheGateway(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk();

        $payment = $this->terminalPayments()->sole();
        $meta = $payment->meta;
        $meta->method = 'interac_present';
        $payment->meta = $meta;
        $payment->save();

        $this->api()->postJson('/api/v1/payments/refund', [
            'id' => $payment->hashed_id,
            'amount' => (float) $payment->amount,
            'gateway_refund' => true,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $this->invoice->hashed_id, 'amount' => (float) $payment->amount]],
        ])->assertJsonFragment(['message' => ctrans('texts.terminal_interac_refund')]);

        $this->assertCount(0, $this->refunds());
    }
}

/* Stripe in memory: PaymentIntents, charges, refunds, idempotency keys, and every request's headers */
final class TerminalStripeFake implements ClientInterface
{
    public array $intents = [];

    public array $requests = [];

    public array $account = [
        'id' => 'acct_terminal_test',
        'object' => 'account',
        'country' => 'US',
        'default_currency' => 'usd',
        'charges_enabled' => true,
    ];

    public array $refunds = [];

    /* Stripe refunds, but the response never arrives */
    public bool $lose_refund_response = false;

    /* Stripe answers a refund with this HTTP status: a 4xx refused it, a 5xx refunded it anyway */
    public ?int $refuse_refund = null;

    private array $idempotency = [];

    private int $sequence = 0;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
    {
        $method = strtolower($method);
        $path = parse_url($absUrl, PHP_URL_PATH);
        $named = [];

        foreach ($headers as $header) {
            [$name, $value] = array_map('trim', explode(':', $header, 2)) + [1 => ''];
            $named[$name] = $value;
        }

        $this->requests[] = ['method' => $method, 'path' => $path, 'headers' => $named, 'params' => $params];

        if ($this->refuse_refund && $path == '/v1/refunds' && $method == 'post') {
            if ($this->refuse_refund >= 500) {
                $this->refund($params, $named['Idempotency-Key'] ?? null);

                return [json_encode(['error' => ['type' => 'api_error', 'message' => 'An unknown error occurred']]), $this->refuse_refund, []];
            }

            return [json_encode(['error' => ['type' => 'invalid_request_error', 'code' => 'charge_disputed', 'message' => 'This charge is disputed']]), $this->refuse_refund, []];
        }

        $response = match (true) {
            $path == '/v1/account' => $this->account,
            $path == '/v1/terminal/connection_tokens' => ['object' => 'terminal.connection_token', 'secret' => 'pst_test_' . (++$this->sequence)],
            $path == '/v1/terminal/locations' && $method == 'get' => ['object' => 'list', 'data' => [['id' => 'tml_1', 'object' => 'terminal.location', 'display_name' => 'Main']], 'has_more' => false, 'url' => '/v1/terminal/locations'],
            $path == '/v1/terminal/locations' => ['id' => 'tml_' . (++$this->sequence), 'object' => 'terminal.location', 'display_name' => $params['display_name'] ?? ''],
            $path == '/v1/refunds' && $method == 'get' => ['object' => 'list', 'data' => array_values($this->refunds[$params['payment_intent']] ?? []), 'has_more' => false, 'url' => '/v1/refunds'],
            $path == '/v1/refunds' => $this->refund($params, $named['Idempotency-Key'] ?? null),
            $path == '/v1/payment_intents' => $this->createIntent($params, $named['Idempotency-Key'] ?? null),
            (bool) preg_match('#^/v1/payment_intents/(pi_\w+)/cancel$#', $path, $m) => $this->setStatus($m[1], 'canceled'),
            (bool) preg_match('#^/v1/payment_intents/(pi_\w+)$#', $path, $m) && $method == 'post' => $this->updateIntent($m[1], $params),
            (bool) preg_match('#^/v1/payment_intents/(pi_\w+)$#', $path, $m) => $this->intents[$m[1]],
            (bool) preg_match('#^/v1/charges/ch_(\w+)$#', $path, $m) => $this->intents["pi_{$m[1]}"]['latest_charge'],
            default => throw new \RuntimeException("Unexpected Stripe request: {$method} {$absUrl}"),
        };

        return [json_encode($response, JSON_THROW_ON_ERROR), 200, []];
    }

    /* The card was read (collect with updatePaymentIntent) */
    public function collect(string $id, string $funding): void
    {
        $this->intents[$id]['status'] = 'requires_confirmation';
        $this->intents[$id]['payment_method'] = [
            'id' => 'pm_' . $id,
            'object' => 'payment_method',
            'type' => 'card_present',
            'card_present' => ['brand' => 'visa', 'last4' => '4242', 'funding' => $funding],
        ];
    }

    /* Confirmed and charged */
    public function charge(string $id, ?int $received = null, string $funding = 'credit', string $method = 'card_present'): void
    {
        $intent = &$this->intents[$id];
        $intent['status'] = 'succeeded';
        $intent['amount_received'] = $received ?? $intent['amount'];
        $intent['latest_charge'] = [
            'id' => 'ch_' . substr($id, 3),
            'object' => 'charge',
            'status' => 'succeeded',
            'payment_intent' => $id,
            'amount_refunded' => 0,
            'metadata' => $intent['metadata'],
            'receipt_url' => 'https://pay.stripe.com/receipts/test',
            'payment_method_details' => [
                'type' => $method,
                $method => ['brand' => 'visa', 'last4' => '4242', 'funding' => $funding, 'exp_month' => 12, 'exp_year' => 2030],
            ],
        ];
    }

    /* A refund made in the Stripe dashboard */
    public function refundInDashboard(string $id, int $amount): void
    {
        $this->refund(['payment_intent' => $id, 'amount' => $amount], null);
    }

    private function refund(array $params, ?string $key): array
    {
        if ($key && isset($this->idempotency[$key])) {
            return $this->idempotency[$key];
        }

        $this->intents[$params['payment_intent']]['latest_charge']['amount_refunded'] += (int) $params['amount'];

        $refund = ['id' => 're_' . (++$this->sequence), 'object' => 'refund', 'status' => 'succeeded', 'amount' => (int) $params['amount'], 'metadata' => $params['metadata'] ?? []];
        $this->refunds[$params['payment_intent']][] = $refund;

        if ($key) {
            $this->idempotency[$key] = $refund;
        }

        if ($this->lose_refund_response) {
            $this->lose_refund_response = false;

            throw new \Stripe\Exception\ApiConnectionException('Could not connect to Stripe');
        }

        return $refund;
    }

    private function createIntent(array $params, ?string $key): array
    {
        if ($key && isset($this->idempotency[$key])) {
            return $this->intents[$this->idempotency[$key]];
        }

        $id = 'pi_test_' . (++$this->sequence);

        $this->intents[$id] = [
            'id' => $id,
            'object' => 'payment_intent',
            'client_secret' => $id . '_secret_abc',
            'amount' => (int) $params['amount'],
            'amount_received' => 0,
            'currency' => $params['currency'],
            'capture_method' => $params['capture_method'],
            'payment_method_types' => $params['payment_method_types'],
            'description' => $params['description'] ?? null,
            'metadata' => $params['metadata'] ?? [],
            'status' => 'requires_payment_method',
            'livemode' => false,
        ];

        if ($key) {
            $this->idempotency[$key] = $id;
        }

        return $this->intents[$id];
    }

    private function updateIntent(string $id, array $params): array
    {
        foreach (['amount', 'description'] as $field) {
            if (array_key_exists($field, $params)) {
                $this->intents[$id][$field] = $field == 'amount' ? (int) $params[$field] : $params[$field];
            }
        }

        return $this->intents[$id];
    }

    private function setStatus(string $id, string $status): array
    {
        $this->intents[$id]['status'] = $status;

        return $this->intents[$id];
    }
}
