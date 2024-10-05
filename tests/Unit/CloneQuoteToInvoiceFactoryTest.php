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

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Factory\InvoiceItemFactory;
use App\Factory\CloneQuoteToInvoiceFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 */
class CloneQuoteToInvoiceFactoryTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testCloneItemSanityInvoice()
    {
                
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100000000;
        $item->type_id = '1';

        $line_items[] = $item;
        
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100000000;
        $item->type_id = '1';

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 2;
        $item->type_id = '3';

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 2;
        $item->type_id = '4';

        $line_items[] = $item;

        $dataX = [
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
            'client_id' => $this->client->hashed_id,
            'line_items' => $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $dataX);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertCount(2, $data['data']['line_items']);

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/quotes?mark_sent=true', $dataX);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertCount(2, $data['data']['line_items']);



        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/credits?mark_sent=true', $dataX);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertCount(2, $data['data']['line_items']);


        $dataX['frequency_id'] = 1;

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/recurring_invoices?mark_sent=true', $dataX);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertCount(2, $data['data']['line_items']);

        $dataX['frequency_id'] = 1;
        $dataX['vendor_id'] = $this->vendor->hashed_id;

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->postJson('/api/v1/purchase_orders?mark_sent=true', $dataX);

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertCount(2, $data['data']['line_items']);

    }

    public function testCloneProperties()
    {
        $invoice = CloneQuoteToInvoiceFactory::create($this->quote, $this->quote->user_id);

        $this->assertNull($invoice->due_date);
        $this->assertNull($invoice->partial_due_date);
        $this->assertNull($invoice->number);
    }

    public function testQuoteToInvoiceConversionService()
    {
        $invoice = $this->quote->service()->convertToInvoice();

        $this->assertTrue($invoice instanceof Invoice);
    }
}
