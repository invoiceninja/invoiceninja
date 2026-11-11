<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Payments;

use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockUnitData;
use Tests\TestCase;

/**
 *
 */
class DeletePaymentTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();
        $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testRegularPayment()
    {
        Invoice::factory()
                ->count(10)
                ->create([
                    'user_id' => $this->user->id,
                    'company_id' => $this->company->id,
                    'client_id' => $this->client->id,
                    'amount' => 101,
                    'balance' => 101,
                    'status_id' => Invoice::STATUS_SENT,
                    'paid_to_date' => 0,
                ]);

        $i = Invoice::where('amount', 101)->where('status_id', 2)->take(1)->get();

        $invoices = $i->map(function ($i) {
            return [
                'invoice_id' => $i->hashed_id,
                'amount' => $i->amount,
            ];
        })->toArray();

        $data = [
            'client_id' => $this->client->hashed_id,
            'amount' => 0,
            'invoices' => $invoices,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $i->fresh()->each(function ($i) {
            $this->assertEquals(0, $i->balance);
            $this->assertEquals(101, $i->paid_to_date);
            $this->assertEquals(Invoice::STATUS_PAID, $i->status_id);
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', ['ids' => [$arr['data']['id']]]);

        $response->assertStatus(200);

        $i->fresh()->each(function ($i) {
            $this->assertEquals(101, $i->balance);
            $this->assertEquals(0, $i->paid_to_date);
            $this->assertEquals(Invoice::STATUS_SENT, $i->status_id);
        });

    }

    /**
     * Bug: When a credit is deleted BEFORE its associated payment is deleted,
     * the credit balance is not restored. This test should FAIL until the fix is applied.
     */
    public function testDeleteCreditThenDeletePaymentRestoresCredit()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'balance' => 0,
            'paid_to_date' => 0,
            'credit_balance' => 0,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        // Create a $10 invoice
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_DRAFT;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();

        $invoice = (new InvoiceSum($invoice))->build()->getInvoice()->service()->markSent()->save();
        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals(10, $invoice->balance);

        // Create a $10 credit
        $credit = CreditFactory::create($this->company->id, $this->user->id);
        $credit->client_id = $client->id;
        $credit->status_id = Credit::STATUS_DRAFT;
        $credit->line_items = $this->buildLineItems();
        $credit->uses_inclusive_taxes = false;
        $credit->save();

        $credit = (new InvoiceSum($credit))->build()->getCredit()->service()->markSent()->save();
        $this->assertEquals(10, $credit->amount);
        $this->assertEquals(10, $credit->balance);

        // Apply credit to invoice via payment API
        $data = [
            'client_id' => $client->hashed_id,
            'invoices' => [
                ['invoice_id' => $invoice->hashed_id, 'amount' => 10],
            ],
            'credits' => [
                ['credit_id' => $credit->hashed_id, 'amount' => 10],
            ],
            'date' => '2020/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $response->assertStatus(200);
        $payment_id = $response->json('data.id');

        // Verify credit was applied
        $credit = $credit->fresh();
        $invoice = $invoice->fresh();
        $client = $client->fresh();

        $this->assertEquals(0, $credit->balance);
        $this->assertEquals(Credit::STATUS_APPLIED, $credit->status_id);
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        // Step 1: Delete the CREDIT first
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits/bulk?action=delete', ['ids' => [$credit->hashed_id]]);

        $response->assertStatus(200);

        $credit = Credit::withTrashed()->find($credit->id);
        $this->assertNotNull($credit->deleted_at, 'Credit should be soft-deleted after bulk delete');
        $this->assertEquals(1, $credit->is_deleted, 'Credit is_deleted should be 1 after bulk delete');

        // Step 2: Delete the PAYMENT second
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', ['ids' => [$payment_id]]);

        $response->assertStatus(200);

        // The credit should STAY deleted, but its balance should be updated
        // so that if it were later restored, the numbers would normalize.
        $credit = Credit::withTrashed()->find($credit->id);
        $client = $client->fresh();

        $this->assertEquals(1, $credit->is_deleted, 'Credit should remain deleted');
        $this->assertNotNull($credit->deleted_at, 'Credit deleted_at should remain set');
        $this->assertEquals(10, $credit->balance, 'Credit balance should be updated to 10');
        $this->assertEquals(0, $credit->paid_to_date, 'Credit paid_to_date should be reset to 0');
    }

    /**
     * Baseline: When a payment is deleted BEFORE the credit, the credit balance
     * IS correctly restored. This test should PASS with current code.
     */
    public function testDeletePaymentThenDeleteCreditWorksCorrectly()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'balance' => 0,
            'paid_to_date' => 0,
            'credit_balance' => 0,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        // Create a $10 invoice
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_DRAFT;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->save();

        $invoice = (new InvoiceSum($invoice))->build()->getInvoice()->service()->markSent()->save();
        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals(10, $invoice->balance);

        // Create a $10 credit
        $credit = CreditFactory::create($this->company->id, $this->user->id);
        $credit->client_id = $client->id;
        $credit->status_id = Credit::STATUS_DRAFT;
        $credit->line_items = $this->buildLineItems();
        $credit->uses_inclusive_taxes = false;
        $credit->save();

        $credit = (new InvoiceSum($credit))->build()->getCredit()->service()->markSent()->save();
        $this->assertEquals(10, $credit->amount);
        $this->assertEquals(10, $credit->balance);

        // Apply credit to invoice via payment API
        $data = [
            'client_id' => $client->hashed_id,
            'invoices' => [
                ['invoice_id' => $invoice->hashed_id, 'amount' => 10],
            ],
            'credits' => [
                ['credit_id' => $credit->hashed_id, 'amount' => 10],
            ],
            'date' => '2020/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $response->assertStatus(200);
        $payment_id = $response->json('data.id');

        // Verify credit was applied
        $credit = $credit->fresh();
        $this->assertEquals(0, $credit->balance);

        // Step 1: Delete the PAYMENT first
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', ['ids' => [$payment_id]]);

        $response->assertStatus(200);

        // Credit balance should be restored
        $credit = $credit->fresh();
        $client = $client->fresh();

        $this->assertEquals(10, $credit->balance, 'Credit balance should be restored to 10');
        $this->assertEquals(Credit::STATUS_SENT, $credit->status_id, 'Credit status should be SENT');
        $this->assertEquals(0, $credit->is_deleted, 'Credit should not be deleted');
        $this->assertEquals(10, $client->credit_balance, 'Client credit_balance should be 10');
    }
}
