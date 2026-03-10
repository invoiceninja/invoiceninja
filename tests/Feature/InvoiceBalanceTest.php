<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Factory\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class InvoiceBalanceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();
    }

    private function makeLineItems(float $cost, int $quantity = 1): array
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = $quantity;
        $item->cost = $cost;

        return [(array) $item];
    }

    private function createInvoiceViaApi(string $clientHashId, array $lineItems): array
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices', [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'client_id' => $clientHashId,
            'line_items' => $lineItems,
        ])->assertStatus(200);

        return $response->json()['data'];
    }

    /**
     * Test that editing a SENT invoice's amount correctly updates the client balance.
     * This is the scenario: invoice at $756, updated to $805.
     */
    public function test_client_balance_updates_when_sent_invoice_amount_changes()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->balance = 0;
        $client->saveQuietly();

        // Create invoice at $756 and mark sent
        $line_items = $this->makeLineItems(756);
        $data = $this->createInvoiceViaApi($client->hashed_id, $line_items);
        $invoice = Invoice::find($this->decodePrimaryKey($data['id']));
        $invoice = $invoice->service()->markSent()->save();

        $client->refresh();
        $this->assertEquals(756, $client->balance, 'Client balance should be 756 after marking sent');

        // Now update the invoice amount to $805 via PUT (the alternativeSave path)
        $new_line_items = $this->makeLineItems(805);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $data['id'], [
            'client_id' => $client->hashed_id,
            'line_items' => $new_line_items,
        ])->assertStatus(200);

        $client->refresh();
        $invoice->refresh();

        $this->assertEquals(805, $invoice->amount, 'Invoice amount should be 805');
        $this->assertEquals(805, $invoice->balance, 'Invoice balance should be 805');
        $this->assertEquals(805, $client->balance, 'Client balance should be 805 after editing sent invoice');
    }

    /**
     * Test that editing a SENT invoice then paying in full results in zero balance.
     * Reproduces the exact $756 -> $805 -> pay $805 -> balance should be $0 scenario.
     */
    public function test_edit_sent_invoice_then_full_payment_results_in_zero_balance()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->balance = 0;
        $client->saveQuietly();

        // Create invoice at $756 and mark sent
        $line_items = $this->makeLineItems(756);
        $data = $this->createInvoiceViaApi($client->hashed_id, $line_items);
        $invoice = Invoice::find($this->decodePrimaryKey($data['id']));
        $invoice = $invoice->service()->markSent()->save();

        $client->refresh();
        $this->assertEquals(756, $client->balance);

        // Update to $805
        $new_line_items = $this->makeLineItems(805);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $data['id'], [
            'client_id' => $client->hashed_id,
            'line_items' => $new_line_items,
        ])->assertStatus(200);

        $client->refresh();
        $this->assertEquals(805, $client->balance, 'Client balance should be 805 after edit');

        // Pay in full - $805
        $invoice->refresh();
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 805,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 805,
                ],
            ],
            'date' => '2024/01/01',
        ])->assertStatus(200);

        $client->refresh();
        $invoice->refresh();

        $this->assertEquals(0, $invoice->balance, 'Invoice balance should be 0 after full payment');
        $this->assertEquals(0, $client->balance, 'Client balance should be 0 after full payment');
    }

    /**
     * Test that marking a draft invoice as sent only increments client balance once.
     */
    public function test_mark_sent_increments_client_balance_once()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->balance = 0;
        $client->saveQuietly();

        $line_items = $this->makeLineItems(500);
        $data = $this->createInvoiceViaApi($client->hashed_id, $line_items);

        $client->refresh();
        $this->assertEquals(0, $client->balance, 'Client balance should be 0 for draft invoice');

        // Mark sent via triggered actions (PUT with mark_sent=true)
        $invoice = Invoice::find($this->decodePrimaryKey($data['id']));
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $data['id'] . '?mark_sent=true', [
            'client_id' => $client->hashed_id,
            'line_items' => $this->makeLineItems(500),
        ])->assertStatus(200);

        $client->refresh();
        $this->assertEquals(500, $client->balance, 'Client balance should be 500 after mark_sent (not double)');
    }

    /**
     * Test that decreasing a sent invoice amount correctly reduces client balance.
     */
    public function test_decreasing_sent_invoice_amount_reduces_client_balance()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->balance = 0;
        $client->saveQuietly();

        // Create and mark sent at $1000
        $line_items = $this->makeLineItems(1000);
        $data = $this->createInvoiceViaApi($client->hashed_id, $line_items);
        $invoice = Invoice::find($this->decodePrimaryKey($data['id']));
        $invoice = $invoice->service()->markSent()->save();

        $client->refresh();
        $this->assertEquals(1000, $client->balance);

        // Reduce to $750
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $data['id'], [
            'client_id' => $client->hashed_id,
            'line_items' => $this->makeLineItems(750),
        ])->assertStatus(200);

        $client->refresh();
        $this->assertEquals(750, $client->balance, 'Client balance should decrease to 750');
    }

    /**
     * Test that editing a sent invoice with no amount change does not affect client balance.
     */
    public function test_editing_sent_invoice_notes_does_not_change_balance()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->balance = 0;
        $client->saveQuietly();

        $line_items = $this->makeLineItems(300);
        $data = $this->createInvoiceViaApi($client->hashed_id, $line_items);
        $invoice = Invoice::find($this->decodePrimaryKey($data['id']));
        $invoice = $invoice->service()->markSent()->save();

        $client->refresh();
        $this->assertEquals(300, $client->balance);

        // Update only notes, not line items
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $data['id'], [
            'client_id' => $client->hashed_id,
            'public_notes' => 'Updated notes only',
            'line_items' => $this->makeLineItems(300),
        ])->assertStatus(200);

        $client->refresh();
        $this->assertEquals(300, $client->balance, 'Client balance should remain 300 when amount unchanged');
    }

    /**
     * Test that multiple sent invoices for same client have correct cumulative balance.
     */
    public function test_multiple_sent_invoices_correct_cumulative_balance()
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->balance = 0;
        $client->saveQuietly();

        // Invoice 1: $200
        $data1 = $this->createInvoiceViaApi($client->hashed_id, $this->makeLineItems(200));
        $inv1 = Invoice::find($this->decodePrimaryKey($data1['id']));
        $inv1->service()->markSent()->save();

        $client->refresh();
        $this->assertEquals(200, $client->balance);

        // Invoice 2: $300
        $data2 = $this->createInvoiceViaApi($client->hashed_id, $this->makeLineItems(300));
        $inv2 = Invoice::find($this->decodePrimaryKey($data2['id']));
        $inv2->service()->markSent()->save();

        $client->refresh();
        $this->assertEquals(500, $client->balance);

        // Edit invoice 1 from $200 to $350
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/' . $data1['id'], [
            'client_id' => $client->hashed_id,
            'line_items' => $this->makeLineItems(350),
        ])->assertStatus(200);

        $client->refresh();
        $this->assertEquals(650, $client->balance, 'Client balance should be 350 + 300 = 650');
    }
}
