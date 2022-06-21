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

namespace Tests\Feature;

use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class DeleteInvoiceTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use MakesHash;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testInvoiceDeletionAfterCancellation()
    {
        $data = [
            'name' => 'A Nice Client',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $client_hash_id = $arr['data']['id'];
        $client = Client::find($this->decodePrimaryKey($client_hash_id));

        $this->assertEquals($client->balance, 0);
        $this->assertEquals($client->paid_to_date, 0);
        //create new invoice.

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $invoice = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $client_hash_id,
            'line_items' => (array) $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $invoice = $invoice->service()->markSent()->save();

        $this->assertEquals(20, $invoice->balance);
        $this->assertEquals(20, $invoice->client->balance);

        $invoice = $invoice->service()->markPaid()->save();

        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(0, $invoice->client->balance);
        $this->assertEquals(20, $invoice->client->paid_to_date);

        //partially refund payment
        $payment = $invoice->fresh()->payments()->first();

        $data = [
            'id' => $payment->id,
            'amount' => 10,
            'invoices' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 10,
                ],
            ],
            'date' => '2020/12/12',
            'gateway_refund' => false,
        ];

        $payment->refund($data);

        //test balances
        $this->assertEquals(10, $payment->fresh()->refunded);
        $this->assertEquals(10, $invoice->client->fresh()->paid_to_date);
        $this->assertEquals(10, $invoice->fresh()->balance);

        //cancel invoice and paid_to_date
        $invoice->fresh()->service()->handleCancellation()->save();

        //test balances and paid_to_date
        $this->assertEquals(0, $invoice->fresh()->balance);
        $this->assertEquals(0, $invoice->client->fresh()->balance);
        $this->assertEquals(10, $invoice->client->fresh()->paid_to_date);

        //delete invoice
        $invoice->fresh()->service()->markDeleted()->save();

        //test balances and paid_to_date
        $this->assertEquals(0, $invoice->fresh()->balance);
        $this->assertEquals(0, $invoice->client->fresh()->balance);
        $this->assertEquals(0, $invoice->client->fresh()->paid_to_date);
    }

    /**
     * @covers App\Services\Invoice\MarkInvoiceDeleted
     */
    public function testInvoiceDeletion()
    {
        $data = [
            'name' => 'A Nice Client',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $client_hash_id = $arr['data']['id'];
        $client = Client::find($this->decodePrimaryKey($client_hash_id));

        $this->assertEquals($client->balance, 0);
        $this->assertEquals($client->paid_to_date, 0);
        //create new invoice.

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $invoice = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $client_hash_id,
            'line_items' => (array) $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $invoice = $invoice->service()->markSent()->save();

        $this->assertEquals(20, $invoice->balance);
        $this->assertEquals(20, $invoice->client->balance);

        //delete invoice
        $data = [
            'ids' => [$invoice_one_hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=delete', $data)->assertStatus(200);

        $invoice = $invoice->fresh();

        $this->assertEquals(20, $invoice->balance);
        $this->assertEquals(0, $invoice->client->balance);
        $this->assertTrue((bool) $invoice->is_deleted);
        $this->assertNotNull($invoice->deleted_at);

        //delete invoice
        $data = [
            'ids' => [$invoice_one_hashed_id],
        ];

        //restore invoice
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=restore', $data)->assertStatus(200);

        $invoice = $invoice->fresh();

        $this->assertEquals(20, $invoice->balance);
        $this->assertFalse((bool) $invoice->is_deleted);
        $this->assertNull($invoice->deleted_at);
        $this->assertEquals(20, $invoice->client->fresh()->balance);
    }

    /**
     * @covers App\Services\Invoice\HandleRestore
     */
    public function testInvoiceDeletionAndRestoration()
    {
        //create new client

        $data = [
            'name' => 'A Nice Client',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $client_hash_id = $arr['data']['id'];
        $client = Client::find($this->decodePrimaryKey($client_hash_id));

        //new client
        $this->assertEquals($client->balance, 0);
        $this->assertEquals($client->paid_to_date, 0);

        //create new invoice.
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = (array) $item;

        $invoice = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $client_hash_id,
            'line_items' => (array) $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_two_hashed_id = $arr['data']['id'];

        //mark as paid

        $data = [
            'amount' => 40.0,
            'client_id' => $client_hash_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice_one_hashed_id,
                    'amount' => 20.0,
                ],
                [
                    'invoice_id' => $invoice_two_hashed_id,
                    'amount' => 20.0,
                ],
            ],
            'date' => '2020/12/01',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_hashed_id = $arr['data']['id'];

        $invoice_one = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));
        $invoice_two = Invoice::find($this->decodePrimaryKey($invoice_two_hashed_id));
        $payment = Payment::find($this->decodePrimaryKey($payment_hashed_id));

        // $this->assertEquals(20, $invoice_one->company_ledger->sortByDesc('id')->first()->balance);

        //test balance
        $this->assertEquals($invoice_one->amount, 20);
        $this->assertEquals($invoice_one->balance, 0);
        $this->assertEquals($invoice_two->amount, 20);
        $this->assertEquals($invoice_two->balance, 0);

        $this->assertEquals($client->fresh()->paid_to_date, 40);
        $this->assertEquals($client->balance, 0);

        //hydrate associated payment
        $this->assertEquals($payment->amount, 40);
        $this->assertEquals($payment->applied, 40);

        //delete invoice
        $data = [
            'ids' => [$invoice_one_hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);

        $this->assertEquals(20, $client->fresh()->paid_to_date);
        $this->assertEquals(0, $client->fresh()->balance);
        $this->assertEquals(20, $payment->fresh()->applied);
        $this->assertEquals(20, $payment->fresh()->amount);

        $invoice_one = $invoice_one->fresh();

        $this->assertTrue((bool) $invoice_one->is_deleted);
        $this->assertNotNull($invoice_one->deleted_at);

        //restore invoice
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertFalse($arr['data'][0]['is_deleted']);

        $invoice_one = $invoice_one->fresh();
        $this->assertFalse((bool) $invoice_one->is_deleted);
        $this->assertNull($invoice_one->deleted_at);

        // $payment = $payment->fresh();

        // $this->assertEquals(40, $payment->fresh()->applied);
        // $this->assertEquals(40, $payment->fresh()->amount);
        // $this->assertEquals(40, $client->fresh()->paid_to_date);
    }
}
