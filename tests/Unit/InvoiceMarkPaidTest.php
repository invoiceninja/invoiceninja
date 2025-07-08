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

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\InvoiceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceMarkPaidTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $invoice;

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceMarkPaidFromDraft()
    {

        
        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;


        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => false
        ]);

        $i->calc()->getInvoice();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/invoices/{$i->hashed_id}?paid=true", []);

        $response->assertStatus(200);


        $this->assertEquals(0, $response->json('data.balance'));
        $this->assertEquals(10, $response->json('data.paid_to_date'));
        $this->assertEquals(4, $response->json('data.status_id'));

        $i = $i->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(10, $i->paid_to_date);
        $this->assertEquals(4, $i->status_id);


    }


    public function testInvoiceMarkPaidFromDraftBulk()
    {

        
        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;


        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => false
        ]);

        $i->calc()->getInvoice();

        $data = [
            'action' => 'mark_paid',
            'ids' => [$i->hashed_id]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/invoices/bulk", $data);

        $response->assertStatus(200);

        $this->assertEquals(0, $response->json('data.0.balance'));
        $this->assertEquals(10, $response->json('data.0.paid_to_date'));
        $this->assertEquals(4, $response->json('data.0.status_id'));

        $i = $i->fresh();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(10, $i->paid_to_date);
        $this->assertEquals(4, $i->status_id);


    }

}
    