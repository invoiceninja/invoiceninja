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

namespace Tests\Feature\Client;

use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyLedger;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Services\Client\Merge;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 * Documents when clients.credit_balance is and is not updated.
 *
 * @see \App\Services\Client\ClientService::adjustCreditBalance()
 * @see \App\Services\Credit\ApplyPayment
 * @see \App\Services\Credit\MarkSent
 * @see \App\Services\Payment\PaymentService::applyCredits()
 */
class ClientCreditBalanceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->makeTestData();

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $this->client->credit_balance = 0;
        $this->client->save();
    }

    public function testDraftCreditCreateLeavesClientCreditBalanceUnchanged(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/', $this->creditPayload());

        $response->assertStatus(200);

        $credit = Credit::find($this->decodePrimaryKey($response->json('data.id')));

        $this->assertNotNull($credit);
        $this->assertSame(Credit::STATUS_DRAFT, $credit->status_id);
        $this->assertEquals(0, $credit->balance);
        $this->assertEquals(20, round((float) $credit->amount, 2));
        $this->assertEquals(0, $this->client->fresh()->credit_balance, 'Draft credits must not change client.credit_balance');
    }

    public function testMarkSentAddsCreditAmountToClientCreditBalance(): void
    {
        $credit = $this->createDraftCredit();

        $credit->service()->markSent()->save();

        $credit = $credit->fresh();
        $client = $this->client->fresh();

        $this->assertEquals(20, round((float) $credit->balance, 2));
        $this->assertEquals(20, round((float) $client->credit_balance, 2));
        $this->assertEquals($client->service()->getCreditBalance(), $client->credit_balance);
    }

    public function testEditingSentCreditUpdatesClientCreditBalanceByTheAmountDelta(): void
    {
        $credit = $this->createSentCredit($this->client, line_count: 2);

        $this->assertEquals(20, round((float) $this->client->fresh()->credit_balance, 2));

        $payload = $this->creditPayload(line_count: 3);
        unset($payload['status_id'], $payload['status']);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/credits/' . $credit->hashed_id, $payload)
            ->assertStatus(200);

        $credit = $credit->fresh();
        $client = $this->client->fresh();

        $this->assertEquals(30, round((float) $credit->balance, 2));
        $this->assertEquals(30, round((float) $client->credit_balance, 2));
    }

    public function testCreateWithMarkSentFlagUpdatesClientCreditBalance(): void
    {
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/?mark_sent=true', array_merge($this->creditPayload(), [
            'mark_sent' => 'true',
        ]))->assertStatus(200);

        $client = $this->client->fresh();

        $this->assertEquals(20, round((float) $client->credit_balance, 2));
        $this->assertEquals($client->service()->getCreditBalance(), $client->credit_balance);
    }

    public function testApplyingCreditThroughPaymentApiReducesClientCreditBalance(): void
    {
        $client = $this->client->fresh();
        $credit = $this->createSentCredit($client, line_count: 1, line_cost: 10);

        $this->assertEquals(10, round((float) $client->fresh()->credit_balance, 2));

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->line_items = $this->buildLineItems(1, 10);
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $this->company);
        $invoice = (new InvoiceSum($invoice))->build()->getInvoice();
        $invoice->service()->markSent()->save();

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', [
            'amount' => 0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                ['invoice_id' => $invoice->hashed_id, 'amount' => 10],
            ],
            'credits' => [
                ['credit_id' => $credit->hashed_id, 'amount' => 10],
            ],
            'date' => '2020/12/12',
        ])->assertStatus(200);

        $client = $client->fresh();
        $credit = $credit->fresh();

        $this->assertEquals(0, round((float) $credit->balance, 2));
        $this->assertEquals(0, round((float) $client->credit_balance, 2));
        $this->assertEquals(0, $client->service()->getCreditBalance());
    }

    /**
     * Client portal / gateway credit application uses PaymentService::applyCredits(),
     * which delegates to Credit\ApplyPayment (company ledger UPDATE_CREDIT). That path
     * must keep clients.credit_balance in sync with credit records — unlike manual
     * POST /api/v1/payments, which uses ApplyCreditPayment instead.
     */
    public function testApplyingCreditThroughPaymentServiceApplyCreditsReducesClientCreditBalance(): void
    {
        $client = $this->client->fresh();
        $credit = $this->createSentCredit($client, line_count: 1, line_cost: 10);

        $this->assertEquals(10, round((float) $client->fresh()->credit_balance, 2));

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->line_items = $this->buildLineItems(1, 10);
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $this->company);
        $invoice = (new InvoiceSum($invoice))->build()->getInvoice();
        $invoice->service()->markSent()->save();

        $applied_amount = 10.0;

        $payment = PaymentFactory::create($this->company->id, $this->user->id, $client->id);
        $payment->amount = 0;
        $payment->applied = 0;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->currency_id = $client->getSetting('currency_id');
        $payment->saveQuietly();

        $payment_hash = PaymentHash::create([
            'hash' => 'credit-balance-portal-' . uniqid(),
            'fee_total' => 0,
            'fee_invoice_id' => $invoice->id,
            'data' => [
                'amount_with_fee' => 0,
                'invoices' => [[
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $applied_amount,
                ]],
                'credits' => $applied_amount,
            ],
        ]);

        $payment->service()->applyCredits($payment_hash)->save();

        $credit = $credit->fresh();
        $client = $client->fresh();
        $invoice = $invoice->fresh();

        $this->assertEquals(0, round((float) $credit->balance, 2), 'Credit record balance must decrease when applied');
        $this->assertEquals(0, round((float) $invoice->balance, 2), 'Invoice must be paid down by applied credit');

        $ledger_entry = CompanyLedger::query()
            ->where('client_id', $client->id)
            ->where('company_ledgerable_id', $credit->id)
            ->where('company_ledgerable_type', Credit::class)
            ->where('activity_id', Activity::UPDATE_CREDIT)
            ->where('adjustment', $applied_amount * -1)
            ->first();

        $this->assertNotNull(
            $ledger_entry,
            'Credit\\ApplyPayment must write an UPDATE_CREDIT company ledger row (ApplyPayment.php ledger path)'
        );

        $this->assertEquals(
            0,
            round((float) $client->credit_balance, 2),
            'clients.credit_balance must decrease when credits are applied via PaymentService::applyCredits()'
        );
        $this->assertEquals($client->service()->getCreditBalance(), $client->credit_balance);
    }

    public function testMergingClientsRecalculatesCreditBalanceFromCreditRecords(): void
    {
        $target = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'credit_balance' => 5,
        ]);

        $source = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'credit_balance' => 0,
        ]);

        $this->createSentCredit($target, 1, 15);
        $this->createSentCredit($source, 1, 25);

        Client::query()->where('id', $target->id)->update(['credit_balance' => 5]);

        $this->assertEquals(5, round((float) Client::find($target->id)->credit_balance, 2), 'Target starts with a stale stored balance');

        $merged = (new Merge(Client::find($target->id), Client::find($source->id)))->run();

        $this->assertEquals(40, round((float) $merged->credit_balance, 2));
        $this->assertEquals($merged->service()->getCreditBalance(), $merged->credit_balance);
        $this->assertNull(Client::withTrashed()->find($source->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function creditPayload(int $line_count = 2): array
    {
        return [
            'status_id' => Credit::STATUS_DRAFT,
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => 'credit-balance-test-' . uniqid(),
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $this->buildLineItems($line_count),
        ];
    }

    private function createDraftCredit(): Credit
    {
        $credit = CreditFactory::create($this->company->id, $this->user->id);
        $credit->client_id = $this->client->id;
        $credit->line_items = $this->buildLineItems();
        $credit->uses_inclusive_taxes = false;
        $credit->discount = 0;
        $credit->tax_rate1 = 0;
        $credit->tax_rate2 = 0;
        $credit->tax_rate3 = 0;

        $credit = (new InvoiceSum($credit))->build()->getCredit();
        $credit->save();

        return $credit->fresh();
    }

    private function createSentCredit(Client $client, int $line_count = 2, float $line_cost = 10): Credit
    {
        $credit = CreditFactory::create($this->company->id, $this->user->id);
        $credit->client_id = $client->id;
        $credit->line_items = $this->buildLineItems($line_count, $line_cost);
        $credit->uses_inclusive_taxes = false;
        $credit->discount = 0;
        $credit->tax_rate1 = 0;
        $credit->tax_rate2 = 0;
        $credit->tax_rate3 = 0;

        $credit = (new InvoiceSum($credit))->build()->getCredit();

        return $credit->service()->markSent()->save();
    }

    /**
     * @return array<int, object>
     */
    private function buildLineItems(int $count = 2, float $cost = 10): array
    {
        $line_items = [];

        for ($i = 0; $i < $count; $i++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            $item->cost = $cost;
            $line_items[] = $item;
        }

        return $line_items;
    }
}
