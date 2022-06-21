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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class InvoiceAmountPaymentTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testPaymentAmountForInvoice()
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
        ])->post('/api/v1/invoices?amount_paid=10', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $this->assertEquals(10, $invoice->balance);
        $this->assertTrue($invoice->payments()->exists());

        $payment = $invoice->payments()->first();

        $this->assertEquals(10, $payment->applied);
        $this->assertEquals(10, $payment->amount);
    }

    public function testMarkPaidRemovesUnpaidGatewayFees()
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

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 5;
        $item->type_id = '3';

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
        ])->post('/api/v1/invoices?mark_sent=true', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $invoice_one_hashed_id = $arr['data']['id'];

        $invoice = Invoice::find($this->decodePrimaryKey($invoice_one_hashed_id));

        $this->assertEquals(25, $invoice->balance);
        $this->assertEquals(25, $invoice->amount);

        $invoice->service()->markPaid()->save();

        $invoice->fresh();

        $this->assertEquals(20, $invoice->amount);
        $this->assertEquals(0, $invoice->balance);
    }
}
