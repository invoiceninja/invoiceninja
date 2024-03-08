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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Helpers\Invoice\InvoiceSum
 */
class InvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $invoice;

    public $invoice_calc;

    public $settings;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->invoice->line_items = $this->buildLineItems();

        $this->invoice->uses_inclusive_taxes = true;

        $this->invoice_calc = new InvoiceSum($this->invoice);
    }

    public function testPartialDueDateCast()
    {
        $i = Invoice::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id
        ]);

        $this->assertNull($i->partial_due_date);

        $i = Invoice::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'partial_due_date' => '2023-10-10',
        ]);

        $this->assertEquals('2023-10-10', $i->partial_due_date->format('Y-m-d'));
    }
   
    public function testMarkPaidWithPartial()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 50;
        $line_items[] = $item;

        $this->invoice->partial = 5;
        $this->invoice->partial_due_date = now()->addDay();
        $this->invoice->due_date = now()->addDays(10);
        $this->invoice->line_items = $line_items;
        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);

        /** @var \App\Models\Invoice $ii */
        $ii = $invoice_calc->build()->getInvoice();
        $invoice = $ii->service()->markSent()->save();

        $this->assertEquals(5, $invoice->partial);
        $this->assertNotNull($invoice->partial_due_date);
        $this->assertEquals(50, $invoice->amount);

        $invoice = $invoice->service()->markPaid()->save();

        $this->assertEquals(0, $invoice->partial);
        $this->assertEquals(0, $invoice->balance);

        $this->assertNull($invoice->partial_due_date);
    }

    public function testGrossTaxAmountCalcuations()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 14191;
        $invoice->is_amount_discount = true;
        $invoice->status_id = 2;
        $line_items = [];
              
        $line_item = new InvoiceItem;
        $line_item->quantity = 1;
        $line_item->cost = 8000;
        $line_item->tax_rate1 = 5;
        $line_item->tax_name1 = 'CA';
        $line_item->product_key = 'line1';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;
        $line_items[] = $line_item;

        $line_item = new InvoiceItem;
        $line_item->quantity = 1;
        $line_item->cost = 9500;
        $line_item->tax_rate1 = 5;
        $line_item->tax_name1 = 'CA';
        $line_item->product_key = 'line2';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;

        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $calc = $invoice->calc();
        $invoice = $calc->getInvoice();

        $this->assertEquals(3474.45, $invoice->amount);
        $this->assertEquals(14191, $invoice->discount);
        $this->assertEquals(165.45, $invoice->total_taxes);

        $item = collect($invoice->line_items)->firstWhere('product_key', 'line1');
        $this->assertEquals(75.63, $item->tax_amount);
        $this->assertEquals(8075.63, $item->gross_line_total);

        $item = collect($invoice->line_items)->firstWhere('product_key', 'line2');
        $this->assertEquals(89.82, $item->tax_amount);
        $this->assertEquals(9589.82, $item->gross_line_total);


    }

    public function testTaskRoundingPrecisionThree()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;

        $line_items = [];
              
        $line_item = new InvoiceItem;
        $line_item->quantity = 25;
        $line_item->cost = 0.333;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem;
        $line_item->quantity = 25;
        $line_item->cost = 0.333;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem;
        $line_item->quantity = 25;
        $line_item->cost = 1.333;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem;
        $line_item->quantity = 25;
        $line_item->cost = 0.267;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem;
        $line_item->quantity = 25;
        $line_item->cost = 0.05;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(57.92, $invoice->amount);

    }

    public function testRoundingWithLargeUnitCostPrecision()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;

        $line_items = [];
              
        $line_item = new InvoiceItem;
        $line_item->quantity = 1;
        $line_item->cost = 82.6446;
        $line_item->tax_rate1 = 21;
        $line_item->tax_name1 = 'Test';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(100, $invoice->amount);

    }

    public function testInclusiveRounding()
    {
        $this->invoice->line_items = [];
        $this->invoice->discount = 0;
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->save();


        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 50;
        $item->tax_name1 = "taxy";
        $item->tax_rate1 = 19;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 50;
        $item->tax_name1 = "taxy";
        $item->tax_rate1 = 19;
        
        $line_items[] = $item;

        $this->invoice->line_items = $line_items;
        $this->invoice->save();

        $invoice_calc = new InvoiceSumInclusive($this->invoice);

        $invoice_calc->build();
        // $this->invoice->save();

        $this->assertEquals($invoice_calc->getTotalTaxes(), 15.96);
    }

    private function buildLineItems()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        return $line_items;
    }

    public function testInvoiceTotals()
    {
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
    }

    public function testInvoiceTotalsWithDiscount()
    {
        $this->invoice->discount = 5;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotal(), 15);
        //$this->assertEquals($this->invoice_calc->getBalance(), 15);
    }

    public function testInvoiceTotalsWithDiscountWithSurcharge()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithInclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        // $this->assertEquals($this->invoice_calc->getTotal(), 21.5);
        //$this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithExclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->uses_inclusive_taxes = false;
        $this->invoice->is_amount_discount = true;

        //$this->invoice_calc = new InvoiceSum($this->invoice, $this->settings);

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        // $this->assertEquals($this->invoice_calc->getGrossSubTotal(), 22);
        $this->assertEquals($this->invoice_calc->getTotal(), 21.5);
        //$this->assertEquals($this->invoice_calc->getBalance(), 21.5);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.5);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithDoubleExclusiveTax()
    {
        $this->invoice_calc = new InvoiceSum($this->invoice);

        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_name2 = 'GST';
        $this->invoice->tax_rate2 = 10;
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 23);
        //$this->assertEquals($this->invoice_calc->getBalance(), 23);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 3);
    }

    public function testLineItemTaxRatesInclusiveTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->custom_value1 = 0;

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getBalance(), 20);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.82);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
    }

    public function testLineItemTaxRatesExclusiveTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;
        $this->invoice->discount = 0;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_name2 = 'GST';
        $this->invoice->tax_rate2 = 10;

        $this->invoice->uses_inclusive_taxes = false;
        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        // $this->assertEquals($this->invoice_calc->getGrossSubTotal(), 22);
        $this->assertEquals($this->invoice_calc->getTotal(), 26);
        //$this->assertEquals($this->invoice_calc->getBalance(), 26);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 4);
        //$this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
    }
}
