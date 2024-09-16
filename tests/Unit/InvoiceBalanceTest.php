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
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Helpers\Invoice\InvoiceSum
 */
class InvoiceBalanceTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceBalances()
    {

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 100;
        $item->type_id = '1';

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'line_items' => [$item],
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'discount' => 0,
            'paid_to_date' => 0,
        ]);


        $this->assertEquals(1, $i->status_id);

        $i = $i->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(100, $i->amount);
        $this->assertEquals(100, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);


        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 30.37;
        $item->type_id = '1';

        $i->line_items = [$item];

        $i = $i->calc()->getInvoice();

        // nlog($i->withoutRelations()->toArray());

        $this->assertEquals(30.37, $i->amount);
        $this->assertEquals(30.37, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);

        $i = $i->service()->applyPaymentAmount(10.37, 'paid')->save();

        // nlog($i->withoutRelations()->toArray());

        $this->assertEquals(30.37, $i->amount);
        $this->assertEquals(20.00, $i->balance);
        $this->assertEquals(3, $i->status_id);
        $this->assertEquals(10.37, $i->paid_to_date);

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 15;
        $item->type_id = '1';

        $i->line_items = [$item];

        $i = $i->calc()->getInvoice();

        $this->assertEquals(15, $i->amount);
        $this->assertEquals(15 - 10.37, $i->balance);
        $this->assertEquals(3, $i->status_id);
        $this->assertEquals(10.37, $i->paid_to_date);


    }


    public function testInvoiceBalancesWithNegatives()
    {

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = -100;
        $item->type_id = '1';

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'line_items' => [$item],
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'discount' => 0,
            'paid_to_date' => 0,
        ]);


        $this->assertEquals(1, $i->status_id);

        $i = $i->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(-100, $i->amount);
        $this->assertEquals(-100, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);


        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = -30.37;
        $item->type_id = '1';

        $i->line_items = [$item];

        $i = $i->calc()->getInvoice();

        $this->assertEquals(-30.37, $i->amount);
        $this->assertEquals(-30.37, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);

        $i = $i->service()->markPaid()->save();

        $this->assertEquals(0, $i->balance);
        $this->assertEquals(-30.37, $i->paid_to_date);
    }




    public function testPurchaseOrderBalances()
    {

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 100;
        $item->type_id = '1';

        $i = PurchaseOrder::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'line_items' => [$item],
            'status_id' => 1,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'discount' => 0,
            'paid_to_date' => 0,
        ]);

        $this->assertEquals(1, $i->status_id);

        $i = $i->calc()->getPurchaseOrder();
        $i = $i->service()->markSent()->save();

        $this->assertEquals(100, $i->amount);
        $this->assertEquals(100, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);


        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 30.37;
        $item->type_id = '1';

        $i->line_items = [$item];

        $i = $i->calc()->getPurchaseOrder();
        $i = $i->service()->markSent()->save();

        $this->assertEquals(30.37, $i->amount);
        $this->assertEquals(30.37, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 10.37;
        $item->type_id = '1';

        $i->line_items = [$item];

        $i = $i->calc()->getPurchaseOrder();
        $i = $i->service()->markSent()->save();

        $this->assertEquals(10.37, $i->amount);
        $this->assertEquals(10.37, $i->balance);
        $this->assertEquals(2, $i->status_id);
        $this->assertEquals(0, $i->paid_to_date);



    }

}
