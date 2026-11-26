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
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Mail\TerminalDeclinedMailer;
use App\Mail\Admin\TerminalDeclinedObject;
use App\Mail\Admin\TerminalDeclineReceiptObject;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\User;
use App\PaymentDrivers\Stripe\Jobs\ChargeRefunded;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentFailureWebhook;
use App\PaymentDrivers\Stripe\CardPresent;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentWebhook;
use App\PaymentDrivers\Stripe\Jobs\ReconcileTerminalPayment;
use App\Repositories\InvoiceRepository;
use App\Services\Client\PaymentMethod;
use App\Services\Invoice\AutoBillInvoice;
use App\Utils\Number;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
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
            'collection_id' => (string) Str::uuid(),
        ], $overrides));
    }

    private function finalize(string $payment_intent): TestResponse
    {
        return $this->api()->postJson($this->url($payment_intent, 'finalize'));
    }

    private function complete(string $payment_intent, ?string $token = null): TestResponse
    {
        return $this->api($token)->postJson($this->url($payment_intent, 'complete'));
    }

    /* An action on a collection, under the invoice it was taken for */
    private function url(string $payment_intent, string $action): string
    {
        $invoice = Invoice::withTrashed()->find($this->collection($payment_intent)->fee_invoice_id);

        return "/api/v1/invoices/{$invoice->hashed_id}/terminal/payment_intents/{$payment_intent}/{$action}";
    }

    /* A collection is the payment hash its PaymentIntent pays */
    private function collection(string $payment_intent): PaymentHash
    {
        return PaymentHash::query()->where('data->terminal->payment_intent_id', $payment_intent)->sole();
    }

    private function collectionStatus(string $payment_intent): int
    {
        return $this->collection($payment_intent)->data->terminal->status_id;
    }

    /* Every collection untouched for this long */
    private function age(int $minutes): void
    {
        PaymentHash::query()->whereNotNull('data->terminal')->update(['created_at' => now()->subMinutes($minutes), 'updated_at' => now()->subMinutes($minutes)]);
    }

    /* Each collection's reconciliation job, as the queue runs it */
    private function reconcile(): void
    {
        PaymentHash::query()->whereNotNull('data->terminal')->pluck('id')->each(fn ($id) => (new ReconcileTerminalPayment($id, null))->handle());
    }

    private function terminalPayments()
    {
        return Payment::query()->where('company_id', $this->company->id)->where('gateway_type_id', GatewayType::CARD_PRESENT);
    }

    private function refunds()
    {
        return collect($this->stripe->requests)->where('path', '/v1/refunds')->where('method', 'post');
    }

    /* Every Invoice Ninja mail queued so far */
    private function mails(): \Illuminate\Support\Collection
    {
        return Bus::dispatched(NinjaMailerJob::class)->map(fn (NinjaMailerJob $job) => $job->nmo)->values();
    }

    private function receipt(string $payment_intent, string $email = 'client@example.com'): TestResponse
    {
        return $this->api()->postJson($this->url($payment_intent, 'receipt'), ['email' => $email]);
    }

    /* What a mail says, subject and both bodies */
    private function mailText(NinjaMailerObject $nmo): string
    {
        $mail = $nmo->mailable->mail_obj;

        return implode("\n", [$mail->subject, $mail->data['body'] ?? '', $mail->data['content'] ?? '', $mail->data['text_body'] ?? '']);
    }

    /* Which notifications a company user takes by email */
    private function notifies(CompanyUser $company_user, array $email): CompanyUser
    {
        /* A pivot has no key of its own to save by */
        CompanyUser::withTrashed()
            ->where('company_id', $company_user->company_id)
            ->where('user_id', $company_user->user_id)
            ->update(['notifications' => json_encode(['email' => $email])]);

        return $company_user;
    }

    /* Another user in the company */
    private function member(array $email): CompanyUser
    {
        $user = User::factory()->create(['account_id' => $this->account->id, 'email' => Str::uuid() . '@example.test']);

        $company_user = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $company_user->is_owner = false;
        $company_user->is_admin = true;
        $company_user->is_locked = false;
        $company_user->save();

        return $this->notifies($company_user, $email);
    }

    /* Each unseen-decline notice queued so far, sent as the queue would */
    private function sendDeclineNotices(): void
    {
        Bus::dispatched(TerminalDeclinedMailer::class)->each(fn (TerminalDeclinedMailer $job) => $job->handle());
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
        $this->age(30);
        $this->reconcile();

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

        /* Only what webhookData() queues: the collection is found by the payment hash in its metadata */
        $object = ['id' => $intent['payment_intent_id'], 'payment_method_types' => ['card_present'], 'metadata' => $this->stripe->intents[$intent['payment_intent_id']]['metadata']];
        (new PaymentIntentWebhook(['object' => $object], $this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertSame(1, $this->terminalPayments()->count());
    }

    public function testReconciliationRecordsAChargeNobodyCompleted(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        /* While the device may still finish it, reconciliation leaves it alone */
        $this->reconcile();
        $this->assertSame(0, $this->terminalPayments()->count());

        $this->age(30);
        $this->reconcile();

        $this->assertSame(1, $this->terminalPayments()->count());
        $this->assertTrue(SystemLog::query()->where('company_id', $this->company->id)->where('event_id', SystemLog::EVENT_PAYMENT_RECONCILIATION_SUCCESS)->exists());
    }

    public function testAChargeReconciliationCannotRecordIsAlertedAndLeftOpen(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        /* Recording fails for a reason that is not Stripe's: the job alerts and fails, and the queue never runs it again */
        ClientContact::query()->where('client_id', $this->client->id)->delete();

        $this->age(30);
        $this->reconcile();
        $this->reconcile();

        $this->assertSame(0, $this->terminalPayments()->count());
        $this->assertSame(CardPresent::STATUS_OPEN, $this->collectionStatus($intent['payment_intent_id']));
        $this->assertSame(1, SystemLog::query()->where('company_id', $this->company->id)->where('event_id', SystemLog::EVENT_PAYMENT_RECONCILIATION_FAILURE)->count());
    }

    public function testAPaymentIntentInFlightIsNeverMarkedCancelled(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->intents[$intent['payment_intent_id']]['status'] = 'processing';

        $this->api()->postJson($this->url($intent['payment_intent_id'], 'cancel'))->assertOk()->assertJson(['status' => 'processing']);

        $this->age(30);
        $this->reconcile();

        $this->assertSame(CardPresent::STATUS_OPEN, $this->collectionStatus($intent['payment_intent_id']));

        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
    }

    public function testAChargeThatHasNotSucceededIsNotRecorded(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);
        $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']['status'] = 'pending';

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'processing']);
        $this->assertSame(0, $this->terminalPayments()->count());
        $this->assertSame(CardPresent::STATUS_OPEN, $this->collectionStatus($intent['payment_intent_id']));

        $this->stripe->intents[$intent['payment_intent_id']]['latest_charge']['status'] = 'succeeded';
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

        $this->age(21);

        $this->collect()->assertOk();
        $this->assertSame(1, CardPresent::collections($this->invoice)->where('data->terminal->status_id', CardPresent::STATUS_CANCELLED)->count());
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

        $this->api()->postJson($this->url($intent['payment_intent_id'], 'cancel'))->assertOk()->assertJson(['status' => 'canceled']);

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

        $this->collect(['amount' => (float) $draft->amount], $draft)->assertOk();

        $this->assertSame(Invoice::STATUS_SENT, $draft->fresh()->status_id);
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
        $stuck = CardPresent::collections($this->invoice)->sole();
        $stuck->created_at = now()->subMinutes(3);
        $stuck->withData('terminal', array_merge((array) $stuck->data->terminal, ['status_id' => CardPresent::STATUS_CREATING, 'payment_intent_id' => null]));

        $this->collect()->assertOk();

        $this->assertSame(CardPresent::STATUS_FAILED, $stuck->fresh()->data->terminal->status_id);
    }

    public function testAnAmountOverTheBalanceIsRefused(): void
    {
        $this->collect(['amount' => (float) $this->invoice->balance + 1])
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

        PaymentHash::create([
            'hash' => Str::random(32), 'fee_total' => 0, 'fee_invoice_id' => $other_invoice->id,
            'data' => ['terminal' => [
                'collection_id' => (string) Str::uuid(), 'company_gateway_id' => $other_gateway->id, 'user_id' => $this->user->id,
                'status_id' => CardPresent::STATUS_OPEN, 'payment_intent_id' => 'pi_other_company', 'currency' => 'usd', 'amount' => 10,
            ]],
        ]);

        /* Under its own invoice it is another company's; under ours it doesn't exist */
        $this->complete('pi_other_company')->assertStatus(401);
        $this->api()->postJson("/api/v1/invoices/{$this->invoice->hashed_id}/terminal/payment_intents/pi_other_company/complete")->assertStatus(400);
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
        $intent = $this->collect()->json();
        $this->stripe->collect($intent['payment_intent_id']);

        /* The client moved to a zero-decimal currency while the card was read */
        $settings = $this->client->settings;
        $settings->currency_id = '45';
        $this->client->settings = $settings;
        $this->client->save();

        $this->finalize($intent['payment_intent_id'])->assertStatus(422)->assertJson(['error_code' => 'unsupported_currency']);
    }

    public function testTheQuoteIsTheAmountInTheClientsCurrency(): void
    {
        $balance = (float) $this->invoice->balance;

        $this->api()->getJson("/api/v1/invoices/{$this->invoice->hashed_id}/terminal/quote?company_gateway_id={$this->company_gateway->hashed_id}&amount={$balance}")
            ->assertOk()
            ->assertExactJson(['amount' => $balance, 'total' => $balance, 'currency_id' => $this->client->getSetting('currency_id')]);
    }

    public function testFinalizeChecksTheInvoiceBeforeTheCardIsCharged(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->collect($intent['payment_intent_id']);
        $this->finalize($intent['payment_intent_id'])->assertOk()->assertExactJson([
            'recollect' => false,
            'amount' => (float) $this->invoice->balance,
            'amount_minor' => (int) round((float) $this->invoice->balance * 100),
        ]);

        /* Reading the card never changes the PaymentIntent */
        $this->assertCount(0, collect($this->stripe->requests)->where('method', 'post')->where('path', "/v1/payment_intents/{$intent['payment_intent_id']}"));

        $this->invoice->fresh()->forceFill(['status_id' => Invoice::STATUS_CANCELLED])->saveQuietly();

        $this->finalize($intent['payment_intent_id'])->assertStatus(409)->assertJson(['error_code' => 'balance_changed']);
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

    public function testAutoBillSettlesAnAbandonedCollectionFirst(): void
    {
        $intent = $this->collect()->json();
        $this->age(30);

        (new AutoBillInvoice($this->invoice->fresh(), $this->company->db))->run();

        $this->assertSame(CardPresent::STATUS_CANCELLED, $this->collectionStatus($intent['payment_intent_id']));
    }

    public function testAutoBillRecordsAChargeNobodyReportedInsteadOfChargingAgain(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);
        $this->age(30);

        (new AutoBillInvoice($this->invoice->fresh(), $this->company->db))->run();

        $this->assertSame(1, $this->terminalPayments()->count());
        $this->assertSame(Invoice::STATUS_PAID, $this->invoice->fresh()->status_id);
    }

    public function testAWriteFromAnOlderCopyNeverUndoesARecording(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->charge($intent['payment_intent_id']);

        /* A request still holding the collection as it was before the charge */
        $older = CardPresent::forCollection($this->collection($intent['payment_intent_id']), $this->client);

        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
        $this->assertFalse($older->updateState(['status_id' => CardPresent::STATUS_CANCELLED]));

        $this->assertSame(CardPresent::STATUS_RECORDED, $this->collectionStatus($intent['payment_intent_id']));
    }

    public function testNothingLogsOrQueuesCardholderData(): void
    {
        Bus::fake([NinjaMailerJob::class, TerminalDeclinedMailer::class]);
        config(['ninja.expanded_logging' => true]);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . json_encode($event->context);
        });

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

        /* A declined card the app showed, then another nobody saw: answered, receipted, reconciled, emailed and cancelled */
        $this->stripe->decline($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['declined' => true]);
        $this->stripe->decline($intent['payment_intent_id'], 'ch_unseen');
        $this->receipt($intent['payment_intent_id'])->assertOk();
        $this->age(10);
        $this->reconcile();
        $this->age(30);
        $this->reconcile();
        $this->sendDeclineNotices();

        $this->assertSame(CardPresent::STATUS_CANCELLED, $this->collectionStatus($intent['payment_intent_id']));
        $this->assertCount(1, Bus::dispatched(TerminalDeclinedMailer::class));
        $this->assertCount(2, $this->mails());

        /* The client secret by its own value: company settings carry keys named *_secret_key */
        $cardholder = ['SECRET NAME', 'fp_SECRET', 'EMV_SECRET', '_secret_abc', 'stolen_card', 'card_declined'];
        $haystacks = array_merge(
            $logged,
            SystemLog::query()->where('company_id', $this->company->id)->get()->map(fn ($log) => json_encode($log->log))->all(),
            Bus::dispatched(NinjaMailerJob::class)->map(fn (NinjaMailerJob $job) => serialize($job))->all(),
            Bus::dispatched(TerminalDeclinedMailer::class)->map(fn (TerminalDeclinedMailer $job) => serialize($job))->all(),
        );

        foreach ($haystacks as $haystack) {
            foreach ($cardholder as $needle) {
                $this->assertStringNotContainsString($needle, $haystack);
            }
        }

        $this->assertSame(0, $this->terminalPayments()->count());
    }

    public function testLocationsAndReceipts(): void
    {
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

        $this->api()->postJson($this->url($intent['payment_intent_id'], 'receipt'), ['email' => 'client@example.com'])
            ->assertStatus(409)->assertJson(['error_code' => 'collection_closed']);

        $this->complete($intent['payment_intent_id'])->assertOk();

        $this->api()->postJson($this->url($intent['payment_intent_id'], 'receipt'), ['email' => 'client@example.com'])->assertOk();

        $update = collect($this->stripe->requests)->first(fn ($request) => str_starts_with($request['path'], '/v1/charges/') && $request['method'] == 'post');
        $this->assertSame('client@example.com', $update['params']['receipt_email']);
        /* Stripe sends these outside the account's email limits: three per payment */
        $this->api()->postJson($this->url($intent['payment_intent_id'], 'receipt'), ['email' => 'b@example.com'])->assertOk();
        $this->api()->postJson($this->url($intent['payment_intent_id'], 'receipt'), ['email' => 'c@example.com'])->assertOk();
        $this->api()->postJson($this->url($intent['payment_intent_id'], 'receipt'), ['email' => 'd@example.com'])
            ->assertStatus(429)->assertJson(['error_code' => 'receipt_limit']);
    }

    public function testConfigListsTheStripeGatewaysCardPresentCouldBeSwitchedOnFor(): void
    {
        $settings = $this->company->settings;
        $settings->country_id = '840';
        $this->company->settings = $settings;
        $this->company->save();

        $switched_off = $this->stripeGateway(['is_enabled' => false]);
        $never_set = $this->stripeGateway([]);
        $never_set->fees_and_limits = (object) [];
        $never_set->save();

        $deleted = $this->stripeGateway(['is_enabled' => false]);
        $deleted->is_deleted = true;
        $deleted->save();

        $config = $this->api()->getJson('/api/v1/terminal/config')->assertOk()->json();

        $this->assertSame([$this->company_gateway->hashed_id], array_column($config['gateways'], 'company_gateway_id'));
        $this->assertEqualsCanonicalizing([$switched_off->hashed_id, $never_set->hashed_id], array_column($config['setup_gateways'], 'company_gateway_id'));
        $this->assertSame(['company_gateway_id', 'label', 'account_country', 'card_payments_active'], array_keys($config['setup_gateways'][0]));
        $this->assertSame('US', $config['setup_gateways'][0]['account_country']);
        $this->assertTrue($config['setup_gateways'][0]['card_payments_active']);
        $this->assertTrue($config['gateways'][0]['card_payments_active']);
        $this->assertSame('US', $config['company_country']);

        /* Stripe still reviewing the account: both lists say so, from the account already read */
        Cache::flush();
        $this->stripe->account['capabilities'] = ['card_payments' => 'pending'];
        $before = count($this->stripe->requests);
        $reviewing = $this->api()->getJson('/api/v1/terminal/config')->assertOk()->json();

        $this->assertSame([false, false], array_column($reviewing['setup_gateways'], 'card_payments_active'));
        $this->assertFalse($reviewing['gateways'][0]['card_payments_active']);
        /* One account read per gateway, the one that already gave its country */
        $this->assertCount(3, collect(array_slice($this->stripe->requests, $before))->where('path', '/v1/account'));

        /* A company with no gateway to set up still learns its country */
        CompanyGateway::query()->where('company_id', $this->company->id)->update(['is_deleted' => true]);

        $this->api()->getJson('/api/v1/terminal/config')->assertOk()
            ->assertJson(['gateways' => [], 'setup_gateways' => [], 'company_country' => 'US']);

        $collector = $this->userToken('["create_payment","view_invoice"]');
        $this->api($collector)->getJson('/api/v1/terminal/config')->assertOk()->assertJsonStructure(['setup_gateways', 'company_country']);

        $viewer = $this->userToken('["view_invoice"]');
        $this->api($viewer)->getJson('/api/v1/terminal/config')->assertStatus(401);
    }

    public function testADeclinedCardKeepsStripesStatusAndSaysWhichCard(): void
    {
        $intent = $this->collect()->json();

        /* Not read yet: nothing was declined */
        $this->assertSame(['status' => 'requires_payment_method'], $this->complete($intent['payment_intent_id'])->assertOk()->json());

        $this->stripe->decline($intent['payment_intent_id']);

        $this->assertSame(
            ['status' => 'requires_payment_method', 'declined' => true, 'brand' => 'visa', 'last4' => '0002'],
            $this->complete($intent['payment_intent_id'])->assertOk()->json(),
        );
        $this->assertSame(CardPresent::STATUS_OPEN, $this->collectionStatus($intent['payment_intent_id']));
        $this->assertSame(0, $this->terminalPayments()->count());

        /* The next card is read and charged as usual */
        $this->stripe->charge($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['status' => 'recorded']);
    }

    public function testAPaymentIntentProcessingAnotherCardIsNotDeclined(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        $intent = $this->collect()->json();
        $this->stripe->processingAfterDecline($intent['payment_intent_id']);

        $this->assertSame(['status' => 'processing'], $this->complete($intent['payment_intent_id'])->assertOk()->json());
        $this->receipt($intent['payment_intent_id'])->assertStatus(409)->assertJson(['error_code' => 'collection_closed']);

        $this->age(10);
        $this->reconcile();
        $this->age(30);
        $this->reconcile();

        $this->assertCount(0, $this->mails());
        $this->assertSame(CardPresent::STATUS_OPEN, $this->collectionStatus($intent['payment_intent_id']));
    }

    public function testADeclinedCardGetsAGenericReceiptOpenOrCancelled(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        /* A test-mode collection (what App Review records): receipts are sent all the same */
        $intent = $this->collect()->json();
        $this->assertFalse($intent['livemode']);

        /* Nothing read yet: nothing to send */
        $this->receipt($intent['payment_intent_id'])->assertStatus(409)->assertJson(['error_code' => 'collection_closed']);

        $this->stripe->decline($intent['payment_intent_id']);

        $this->receipt($intent['payment_intent_id'], 'customer@example.com')->assertOk();

        $mail = $this->mails()->sole();
        $text = $this->mailText($mail);

        $this->assertSame('customer@example.com', $mail->to_user->email);
        $this->assertFalse($mail->to_user->exists);
        $this->assertStringContainsString($this->company->present()->name(), $text);
        $this->assertStringContainsString($this->invoice->number, $text);
        $this->assertStringContainsString(Number::formatMoney($this->invoice->balance, $this->client->fresh()), $text);
        $this->assertStringContainsString('Visa', $text);
        $this->assertStringContainsString('0002', $text);
        $this->assertStringContainsString(ctrans('texts.terminal_declined'), $text);

        /* Never why: Stripe asks for a generic decline, whatever the code */
        foreach (['card_declined', 'stolen_card', 'issuer_declined', 'declined_by_network', 'SECRET NAME', 'fp_SECRET', 'EMV_SECRET'] as $never) {
            $this->assertStringNotContainsString($never, $text);
        }

        /* Not sent to the client's contacts, and Stripe's own receipt is untouched */
        $this->assertCount(0, collect($this->stripe->requests)->filter(fn ($request) => str_starts_with($request['path'], '/v1/charges/') && $request['method'] == 'post'));

        /* Cancelled since (the app's fallback): still the customer's receipt */
        $this->api()->postJson($this->url($intent['payment_intent_id'], 'cancel'))->assertOk()->assertJson(['status' => 'canceled']);
        $this->assertSame(CardPresent::STATUS_CANCELLED, $this->collectionStatus($intent['payment_intent_id']));

        $this->receipt($intent['payment_intent_id'], 'b@example.com')->assertOk();
        $this->receipt($intent['payment_intent_id'], 'c@example.com')->assertOk();
        $this->receipt($intent['payment_intent_id'], 'd@example.com')->assertStatus(429)->assertJson(['error_code' => 'receipt_limit']);

        $this->assertCount(3, $this->mails());

        $terminal = $this->collection($intent['payment_intent_id'])->data->terminal;
        $this->assertSame(3, $terminal->decline_receipts_sent);
        $this->assertSame(0, $terminal->receipts_sent);
    }

    public function testACardDeclinedWhileNobodyWatchedIsEmailedOnceBeforeItIsCancelled(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);

        /* The device is still on it */
        $this->reconcile();
        $this->assertCount(0, $this->mails());

        $this->age(10);
        $older = CardPresent::forCollection($this->collection($intent['payment_intent_id']), $this->client);

        $this->reconcile();

        $mail = $this->mails()->sole();
        $data = $mail->mailable->mail_obj->data;
        $invoice = $this->invoice->fresh();

        $this->assertSame($this->user->id, $mail->to_user->id);
        $client = $this->client->fresh();
        $this->assertSame("Card payment declined: invoice {$invoice->number} ({$client->present()->name()})", $mail->mailable->mail_obj->subject);
        $this->assertSame(
            'A card payment of ' . Number::formatMoney($invoice->balance, $client) . " on invoice {$invoice->number} ({$client->present()->name()}) was declined, so the customer wasn't charged. Open the invoice to take the payment again.",
            $data['text_body'],
        );
        $this->assertStringContainsString("/app/invoices/{$invoice->hashed_id}?company={$this->company->hashed_id}", $data['url']);

        $terminal = $this->collection($intent['payment_intent_id'])->data->terminal;
        $this->assertNotNull($terminal->decline_notified_at);
        $this->assertSame('ch_' . substr($intent['payment_intent_id'], 3), $terminal->decline_notified_charge);
        $this->assertSame(CardPresent::STATUS_OPEN, $terminal->status_id);

        /* Another copy that had not seen the notice: still once */
        $older->notifyDecline($invoice);

        /* The abandoned collection is cancelled, and nobody is told again */
        $this->age(30);
        $this->reconcile();

        $this->assertCount(1, $this->mails());
        $this->assertSame(CardPresent::STATUS_CANCELLED, $this->collectionStatus($intent['payment_intent_id']));
    }

    public function testADeclineFoundAtTheStaleLookIsEmailedBeforeTheCancel(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);

        $this->age(30);
        $this->reconcile();

        $this->assertCount(1, $this->mails());
        $this->assertSame(CardPresent::STATUS_CANCELLED, $this->collectionStatus($intent['payment_intent_id']));
        $this->assertNotNull($this->collection($intent['payment_intent_id'])->data->terminal->decline_notified_at);
    }

    public function testADeclineTheAppShowedIsNotEmailedButTheNextCardsIs(): void
    {
        Bus::fake([TerminalDeclinedMailer::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['declined' => true]);

        $this->age(10);
        $this->reconcile();
        $this->assertCount(0, Bus::dispatched(TerminalDeclinedMailer::class));

        /* Another card, declined with nobody watching: that charge is told */
        $this->stripe->decline($intent['payment_intent_id'], 'ch_second_card');
        $this->age(10);
        $this->reconcile();
        $this->reconcile();

        $this->assertCount(1, Bus::dispatched(TerminalDeclinedMailer::class));
        $this->assertSame('ch_second_card', $this->collection($intent['payment_intent_id'])->data->terminal->decline_notified_charge);
    }

    public function testAutoBillSettlingACollectionNeverSendsTheNotice(): void
    {
        Bus::fake([TerminalDeclinedMailer::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);
        $this->age(10);

        $this->assertTrue(CardPresent::settle($this->invoice, config('database.default')));

        $this->assertCount(0, Bus::dispatched(TerminalDeclinedMailer::class));
        $this->assertObjectNotHasProperty('decline_notified_charge', $this->collection($intent['payment_intent_id'])->data->terminal);

        $this->reconcile();
        $this->assertCount(1, Bus::dispatched(TerminalDeclinedMailer::class));
    }

    public function testClaimingTheNoticeLeavesTheCollectionIdle(): void
    {
        Bus::fake([TerminalDeclinedMailer::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);
        $this->age(10);

        $before = $this->collection($intent['payment_intent_id'])->updated_at;

        $this->reconcile();

        $this->assertCount(1, Bus::dispatched(TerminalDeclinedMailer::class));
        $this->assertEquals($before, $this->collection($intent['payment_intent_id'])->updated_at);
    }

    public function testADeclineIsReadFromAnUnexpandedLatestCharge(): void
    {
        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);
        $this->stripe->ignore_expand = true;

        $this->complete($intent['payment_intent_id'])->assertOk()
            ->assertJson(['status' => 'requires_payment_method', 'declined' => true, 'brand' => 'visa', 'last4' => '0002']);

        $this->assertCount(1, collect($this->stripe->requests)->filter(fn ($request) => $request['method'] == 'get' && str_starts_with($request['path'], '/v1/charges/')));
    }

    public function testTheDeclineReceiptLimitHoldsFromWhereverTheCountIs(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);

        PaymentHash::query()->whereKey($this->collection($intent['payment_intent_id'])->id)->update(['data->terminal->decline_receipts_sent' => 2]);

        $this->receipt($intent['payment_intent_id'])->assertOk();
        $this->assertSame(3, $this->collection($intent['payment_intent_id'])->data->terminal->decline_receipts_sent);

        $this->receipt($intent['payment_intent_id'])->assertStatus(429)->assertJson(['error_code' => 'receipt_limit']);
        $this->assertCount(1, $this->mails());

        /* Already at the limit, whatever this request's copy says */
        $collection = CardPresent::forCollection($this->collection($intent['payment_intent_id']), $this->client);
        PaymentHash::query()->whereKey($this->collection($intent['payment_intent_id'])->id)->update(['data->terminal->decline_receipts_sent' => 3]);
        $stale = $collection->state();
        $stale->decline_receipts_sent = 0;

        try {
            $collection->receipt('late@example.com');
            $this->fail('A fourth decline receipt was sent');
        } catch (\App\Exceptions\TerminalException $e) {
            $this->assertSame('receipt_limit', $e->error_code);
        }

        $this->assertCount(1, $this->mails());
        $this->assertSame(3, $this->collection($intent['payment_intent_id'])->data->terminal->decline_receipts_sent);
    }

    public function testTheDeclineNoticeAlwaysReachesTheMerchant(): void
    {
        $invoice = $this->invoice->fresh();

        $recipients = function (int $starter) use ($invoice) {
            Bus::fake([NinjaMailerJob::class]);
            (new TerminalDeclinedMailer($invoice, $this->company->fresh(), $starter, '$10.00'))->handle();

            return $this->mails()->map(fn (NinjaMailerObject $nmo) => $nmo->to_user->id)->all();
        };

        /* The starter: with the default settings, and with no payment failure notifications at all */
        $starter = $this->member(['invoice_sent_all']);
        $this->assertSame([$starter->user_id], $recipients($starter->user_id));

        $this->notifies($starter, []);
        $this->assertSame([$starter->user_id], $recipients($starter->user_id));

        /* The starter is also the owner: one copy */
        $this->notifies($this->cu, ['payment_failure_all']);
        $this->assertSame([$this->user->id], $recipients($this->user->id));

        /* Removed: whoever takes every payment failure, once each */
        CompanyUser::query()->where('company_id', $this->company->id)->where('user_id', $starter->user_id)->delete();
        $other = $this->member(['all_notifications']);
        $this->assertEqualsCanonicalizing([$this->user->id, $other->user_id], $recipients($starter->user_id));

        /* Nobody takes them: the account owner */
        $this->notifies($this->cu, ['invoice_sent_all']);
        $this->notifies($other, []);
        $this->assertSame([$this->user->id], $recipients($starter->user_id));
    }

    public function testAnArchivedStarterIsNotTheMerchantAnyMore(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        $starter = $this->member(['all_notifications']);
        $other = $this->member(['payment_failure_all']);

        /* Archiving keeps the company user and soft-deletes the user */
        User::query()->find($starter->user_id)->delete();

        (new TerminalDeclinedMailer($this->invoice->fresh(), $this->company->fresh(), $starter->user_id, '$10.00'))->handle();
        $this->assertSame([$other->user_id], $this->mails()->map(fn (NinjaMailerObject $nmo) => $nmo->to_user->id)->all());

        /* Nobody opted in either: the owner, never the archived starter */
        $this->notifies($other, []);
        Bus::fake([NinjaMailerJob::class]);
        (new TerminalDeclinedMailer($this->invoice->fresh(), $this->company->fresh(), $starter->user_id, '$10.00'))->handle();
        $this->assertSame([$this->user->id], $this->mails()->map(fn (NinjaMailerObject $nmo) => $nmo->to_user->id)->all());
    }

    public function testOnceTheStarterIsGoneTheInvoicesOwnUsersTakeTheirOwnFailures(): void
    {
        Bus::fake([NinjaMailerJob::class]);

        $starter = $this->member([]);
        $assignee = $this->member(['payment_failure_user']);
        $this->member(['payment_failure_user']);
        $this->notifies($this->cu, []);

        Invoice::query()->whereKey($this->invoice->id)->update(['assigned_user_id' => $assignee->user_id]);
        CompanyUser::query()->where('company_id', $this->company->id)->where('user_id', $starter->user_id)->delete();

        (new TerminalDeclinedMailer($this->invoice->fresh(), $this->company->fresh(), $starter->user_id, '$10.00'))->handle();

        /* Only the assignee: payment_failure_user is about one's own invoices, and the owner was not needed */
        $this->assertSame([$assignee->user_id], $this->mails()->map(fn (NinjaMailerObject $nmo) => $nmo->to_user->id)->all());
    }

    public function testAJobQueuedBeforeTheFlagExistedStillNotifiesAndCancels(): void
    {
        Bus::fake([TerminalDeclinedMailer::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);
        $this->age(30);

        /* Its payload has no notify_decline: unserializing sets only the properties it carries */
        $job = (new \ReflectionClass(ReconcileTerminalPayment::class))->newInstanceWithoutConstructor();
        $job->payment_hash_id = $this->collection($intent['payment_intent_id'])->id;
        $job->db = null;

        $job->handle();

        $this->assertCount(1, Bus::dispatched(TerminalDeclinedMailer::class));
        $this->assertSame(CardPresent::STATUS_CANCELLED, $this->collectionStatus($intent['payment_intent_id']));
    }

    public function testADeclineTheAppShowsAfterTheLookReadItIsNotEmailed(): void
    {
        Bus::fake([TerminalDeclinedMailer::class]);

        $intent = $this->collect()->json();
        $this->stripe->decline($intent['payment_intent_id']);

        /* The look read the collection, then the app's complete showed the decline */
        $look = CardPresent::forCollection($this->collection($intent['payment_intent_id']), $this->client);
        $this->complete($intent['payment_intent_id'])->assertOk()->assertJson(['declined' => true]);

        $look->notifyDecline($this->invoice->fresh());

        $this->assertCount(0, Bus::dispatched(TerminalDeclinedMailer::class));
        $this->assertObjectNotHasProperty('decline_notified_charge', $this->collection($intent['payment_intent_id'])->data->terminal);
    }

    public function testBothDeclineMailsRender(): void
    {
        $client = $this->client->fresh();

        $notice = (new TerminalDeclinedObject($this->company, 'INV-7', $client->present()->name(), '$10.00', 'https://app.example.test/app/invoices/x'))->build();

        $html = (new NinjaMailer($notice))->render();
        $this->assertStringContainsString('Card payment declined: invoice INV-7', $html);
        $this->assertStringContainsString('https://app.example.test/app/invoices/x', $html);
        $this->assertStringContainsString(ctrans('texts.view_invoice'), $html);
        $this->assertStringContainsString('was declined', view($notice->text_view, $notice->data)->render());

        /* The client's own clock, over its group's and the company's */
        $military = function (bool $on) use ($client) {
            $settings = $client->settings;
            $settings->military_time = $on;
            $client->settings = $settings;
            $client->save();

            return $client->fresh();
        };

        $receipt = (new TerminalDeclineReceiptObject($this->company, $military(false), 'INV-7', '$10.00', 'mastercard', '0002', 1767268800))->build();

        $html = (new NinjaMailer($receipt))->render();
        $text = view($receipt->text_view, $receipt->data)->render();

        foreach ([$html, $text] as $rendered) {
            $this->assertStringContainsString('INV-7', $rendered);
            $this->assertStringContainsString('MasterCard', $rendered);
            $this->assertStringContainsString('0002', $rendered);
            $this->assertStringContainsString(ctrans('texts.terminal_declined'), $rendered);
        }

        $this->assertMatchesRegularExpression('/\d:\d{2} (am|pm)/', $text);
        $this->assertStringContainsString('<td style="padding: 4px 12px;">', $html);

        $receipt = (new TerminalDeclineReceiptObject($this->company, $military(true), 'INV-7', '$10.00', 'somecard', '0002', 1767268800))->build();
        $text = view($receipt->text_view, $receipt->data)->render();

        $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $text);
        $this->assertDoesNotMatchRegularExpression('/\d (am|pm)/', $text);
        $this->assertStringContainsString('Somecard', $text);
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

/* Stripe in memory: PaymentIntents, charges, idempotency keys, and every request's headers */
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

    /* Charges by id, for those not named after their PaymentIntent */
    public array $charges = [];

    /* Answer a PaymentIntent with its latest_charge as an id, as Stripe does without expand */
    public bool $ignore_expand = false;

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

        $response = match (true) {
            $path == '/v1/account' => $this->account,
            $path == '/v1/terminal/connection_tokens' => ['object' => 'terminal.connection_token', 'secret' => 'pst_test_' . (++$this->sequence)],
            $path == '/v1/terminal/locations' && $method == 'get' => ['object' => 'list', 'data' => [['id' => 'tml_1', 'object' => 'terminal.location', 'display_name' => 'Main']], 'has_more' => false, 'url' => '/v1/terminal/locations'],
            $path == '/v1/terminal/locations' => ['id' => 'tml_' . (++$this->sequence), 'object' => 'terminal.location', 'display_name' => $params['display_name'] ?? ''],
            $path == '/v1/payment_intents' => $this->createIntent($params, $named['Idempotency-Key'] ?? null),
            (bool) preg_match('#^/v1/payment_intents/(pi_\w+)/cancel$#', $path, $m) => $this->setStatus($m[1], 'canceled'),
            (bool) preg_match('#^/v1/payment_intents/(pi_\w+)$#', $path, $m) && $method == 'get' => $this->ignore_expand && is_array($this->intents[$m[1]]['latest_charge'] ?? null)
                ? array_merge($this->intents[$m[1]], ['latest_charge' => $this->intents[$m[1]]['latest_charge']['id']])
                : $this->intents[$m[1]],
            (bool) preg_match('#^/v1/charges/(ch_\w+)$#', $path, $m) => $this->charges[$m[1]] ?? $this->intents['pi_' . substr($m[1], 3)]['latest_charge'],
            default => throw new \RuntimeException("Unexpected Stripe request: {$method} {$absUrl}"),
        };

        return [json_encode($response, JSON_THROW_ON_ERROR), 200, []];
    }

    /* The card was read */
    public function collect(string $id): void
    {
        $this->intents[$id]['status'] = 'requires_confirmation';
    }

    /* Confirmed and charged */
    public function charge(string $id, ?int $received = null, string $method = 'card_present'): void
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
                $method => ['brand' => 'visa', 'last4' => '4242', 'funding' => 'credit', 'exp_month' => 12, 'exp_year' => 2030],
            ],
        ];
    }

    /* The card was declined: Stripe waits for another, and the failed charge is its latest */
    public function decline(string $id, ?string $charge_id = null): void
    {
        $intent = &$this->intents[$id];
        $intent['status'] = 'requires_payment_method';
        $intent['latest_charge'] = [
            'id' => $charge_id ?? 'ch_' . substr($id, 3),
            'object' => 'charge',
            'status' => 'failed',
            'amount' => $intent['amount'],
            'currency' => $intent['currency'],
            'created' => now()->timestamp,
            'payment_intent' => $id,
            'amount_refunded' => 0,
            'failure_code' => 'card_declined',
            'failure_message' => 'Your card was declined.',
            'outcome' => ['type' => 'issuer_declined', 'reason' => 'stolen_card', 'network_status' => 'declined_by_network'],
            'metadata' => $intent['metadata'],
            'payment_method_details' => [
                'type' => 'card_present',
                'card_present' => [
                    'brand' => 'visa', 'last4' => '0002', 'funding' => 'credit', 'exp_month' => 12, 'exp_year' => 2030,
                    'cardholder_name' => 'SECRET NAME', 'fingerprint' => 'fp_SECRET', 'emv_auth_data' => 'EMV_SECRET',
                ],
            ],
        ];

        $this->charges[$intent['latest_charge']['id']] = $intent['latest_charge'];
    }

    /* Another card is being charged after one was declined: the PaymentIntent still carries the failed charge */
    public function processingAfterDecline(string $id): void
    {
        $this->decline($id);
        $this->intents[$id]['status'] = 'processing';
    }

    /* A refund made in the Stripe dashboard */
    public function refundInDashboard(string $id, int $amount): void
    {
        $this->intents[$id]['latest_charge']['amount_refunded'] += $amount;
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
            'latest_charge' => null,
            'livemode' => false,
        ];

        if ($key) {
            $this->idempotency[$key] = $id;
        }

        return $this->intents[$id];
    }

    private function setStatus(string $id, string $status): array
    {
        $this->intents[$id]['status'] = $status;

        return $this->intents[$id];
    }
}
