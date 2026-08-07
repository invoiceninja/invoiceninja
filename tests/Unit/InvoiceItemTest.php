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
use App\Helpers\Invoice\InvoiceItemSum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *   App\Helpers\Invoice\InvoiceItemSum
 */
class InvoiceItemTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    /**
     * TDD: does the float-summation noise that affected the inclusive total_taxes
     * prop also affect the EXCLUSIVE path? 1 @ 10% + 20% exclusive: per-tax
     * 0.10 + 0.20, whose raw float sum is 0.30000000000000004. If total_taxes is
     * assigned raw, this leaks; if it is precision-rounded, it is a clean 0.30.
     * assertSame is strict (no epsilon), so it catches any sub-cent residual.
     */
    public function testExclusiveTotalTaxesIsPrecisionRoundedSimpleFloatTrap()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 0;

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 1;
        $line_item->tax_name1 = 'TaxA';
        $line_item->tax_rate1 = 10;
        $line_item->tax_name2 = 'TaxB';
        $line_item->tax_rate2 = 20;
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->is_amount_discount = false;

        $invoice->line_items = [$line_item];
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        // sanity: the raw float sum genuinely carries noise
        $this->assertNotSame(0.3, 0.1 + 0.2);

        // the prop must be a clean, precision-rounded value
        $this->assertEquals(0.3, $invoice->total_taxes);
        $this->assertSame(round((float) $invoice->total_taxes, 2), (float) $invoice->total_taxes);
    }

    /**
     * TDD: same question with three taxes that accumulate noise across a
     * multi-quantity line. 3 x cost 1 (line_total 3) @ 10% + 20% + 5%:
     * per-tax 0.30 + 0.60 + 0.15. The prop must reconcile with no float dust.
     */
    public function testExclusiveTotalTaxesIsPrecisionRoundedTripleTax()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 0;

        $line_item = new InvoiceItem();
        $line_item->quantity = 3;
        $line_item->cost = 1;
        $line_item->tax_name1 = 'TaxA';
        $line_item->tax_rate1 = 10;
        $line_item->tax_name2 = 'TaxB';
        $line_item->tax_rate2 = 20;
        $line_item->tax_name3 = 'TaxC';
        $line_item->tax_rate3 = 5;
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->is_amount_discount = false;

        $invoice->line_items = [$line_item];
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        // 0.30 + 0.60 + 0.15 = 1.05
        $this->assertEquals(1.05, $invoice->total_taxes);
        $this->assertSame(round((float) $invoice->total_taxes, 2), (float) $invoice->total_taxes);
    }

    public function testNetCost()
    {

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_name2 = '';
        $invoice->tax_name3 = '';

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 22;
        $line_item->tax_name1 = 'Km';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->is_amount_discount = false;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(100, $invoice->amount);
        $this->assertEquals(18.03, $invoice->total_taxes);


    }

    public function testEdgeCasewithDiscountsPercentageAndTaxCalculations()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_name2 = '';
        $invoice->tax_name3 = '';

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 22;
        $line_item->tax_name1 = 'Km';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->is_amount_discount = false;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(122, $invoice->amount);
        $this->assertEquals(22, $invoice->total_taxes);
    }


    public function testDiscountsWithInclusiveTaxes()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = true;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(90, $invoice->amount);
        $this->assertEquals(8.18, $invoice->total_taxes);
    }


    public function testDiscountsWithInclusiveTaxesNegativeInvoice()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = true;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-90, $invoice->amount);
        $this->assertEquals(-8.18, $invoice->total_taxes);
    }

    public function testDicountsWithTaxes()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99, $invoice->amount);
        $this->assertEquals(9, $invoice->total_taxes);
    }


    public function testDicountsWithTaxesNegativeInvoice()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-99, $invoice->amount);
        $this->assertEquals(-9, $invoice->total_taxes);
    }

    public function testDicountsWithTaxesPercentage()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99, $invoice->amount);
        $this->assertEquals(9, $invoice->total_taxes);
    }

    public function testDicountsWithTaxesPercentageOnLine()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = false;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->is_amount_discount = false;
        $line_item->discount = 10;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(81, $invoice->amount);
        $this->assertEquals(7.36, $invoice->total_taxes);
    }

    public function testDicountsWithExclusiveTaxesPercentageOnLine()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->is_amount_discount = false;
        $line_item->discount = -10;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-133.1, $invoice->amount);
        $this->assertEquals(-12.1, $invoice->total_taxes);
    }


    public function testDicountsWithTaxesNegativeInvoicePercentage()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-121, $invoice->amount);
        $this->assertEquals(-10, $invoice->discount);
        $this->assertEquals(-11, $invoice->total_taxes);
    }



    public function testDicountPercentageWithTaxes()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99, $invoice->amount);
        $this->assertEquals(9, $invoice->total_taxes);
    }




    public function testInvoiceItemTotalSimple()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithGrossTaxes()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 10);
        $this->assertEquals($item_calc->getGrossLineTotal(), 11);
    }

    public function testInvoiceItemTotalSimpleWithDiscount()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountAndGrossLineTotal()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;
        $item->tax_rate1 = 10;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 8);
        $this->assertEquals($item_calc->getGrossLineTotal(), 8.8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecision()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 7.48);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.80);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleExclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;
        $item->tax_rate1 = 10;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.75);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 2.06);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleExclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'VAT';
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        nlog($item_calc->getGroupedTaxes());

        $this->assertEquals($item_calc->getTotalTaxes(), 2.06);
        $this->assertEquals($item_calc->getGroupedTaxes()->count(), 2);
    }

    public function testNetCostWithDoubleTaxInclusive()
    {

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = false;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_rate2 = 5;
        $line_item->tax_name1 = 'GST';
        $line_item->tax_name2 = 'VAT';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(100, $invoice->amount);
        // Tax-anchored: round(100*10/115)=8.70 + round(100*5/115)=4.35 => 13.05
        $this->assertEquals(13.05, $invoice->total_taxes);

    }

    public function testNetCostWithHighTaxRatesInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 25;
        $line_item->tax_rate2 = 20;
        $line_item->tax_name1 = 'Tax1';
        $line_item->tax_name2 = 'Tax2';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(100, $invoice->amount);
        // Tax-anchored: round(100*25/145)=17.24 + round(100*20/145)=13.79 => 31.03
        $this->assertEquals(31.03, $invoice->total_taxes);
        $this->assertEquals(68.97, $item->net_cost);
    }

    public function testNetCostWithTripleTaxInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 7;
        $line_item->tax_rate2 = 5;
        $line_item->tax_rate3 = 3;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(100, $invoice->amount);
        // Tax-anchored: round(100*7/115)=6.09 + round(100*5/115)=4.35 + round(100*3/115)=2.61 => 13.05
        $this->assertEquals(13.05, $invoice->total_taxes);
        $this->assertEquals(86.95, $item->net_cost);
    }

    public function testNetCostWithFractionalTaxRatesInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 7.5;
        $line_item->tax_rate2 = 2.75;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(100, $invoice->amount);
        // Tax-anchored: round(100*7.5/110.25)=6.80 + round(100*2.75/110.25)=2.49 => 9.29
        $this->assertEquals(9.29, $invoice->total_taxes);
        $this->assertEquals(90.71, $item->net_cost);
    }

    public function testNetCostWithHighValueAndMultipleTaxesInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 1000;
        $line_item->tax_rate1 = 15;
        $line_item->tax_rate2 = 10;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(1000, $invoice->amount);
        // Tax-anchored: round(1000*15/125)=120.00 + round(1000*10/125)=80.00 => 200.00
        $this->assertEquals(200.00, $invoice->total_taxes);
        $this->assertEquals(800, $item->net_cost);
    }

    public function testNetCostWithLowTaxRatesInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 1.5;
        $line_item->tax_rate2 = 0.5;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(100, $invoice->amount);
        // Tax-anchored: round(100*1.5/102)=1.47 + round(100*0.5/102)=0.49 => 1.96
        $this->assertEquals(1.96, $invoice->total_taxes);
        $this->assertEquals(98.04, $item->net_cost);
    }

    public function testNetCostWithEqualTaxRatesInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_rate2 = 10;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(100, $invoice->amount);
        // Tax-anchored: round(100*10/120)=8.33 x2 => 16.66, net absorbs residual (83.34)
        $this->assertEquals(16.66, $invoice->total_taxes);
        $this->assertEquals(83.34, $item->net_cost);
    }

    public function testNetCostWithZeroAndNonZeroTaxesInclusive()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;

        $line_items = [];
        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_name1 = 'Tax1';
        $line_item->tax_rate1 = 0;
        $line_item->tax_name2 = 'Tax2';
        $line_item->tax_rate2 = 15;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $item = $invoice->line_items[0];

        $this->assertEquals(100, $invoice->amount);
        $this->assertEquals(13.04, $invoice->total_taxes);
        $this->assertEquals(86.96, $item->net_cost);
    }

}
