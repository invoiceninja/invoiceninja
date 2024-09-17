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

use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceItemSum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Helpers\Invoice\InvoiceItemSum
 */
class InvoiceItemV2Test extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
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

        $this->assertTrue(is_array($item_calc->getLineItems()));
        $this->assertEquals(count($item_calc->getLineItems()), 1);

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->quantity, $item->quantity);
        $this->assertEquals($line_items[0]->cost, $item->cost);
        $this->assertEquals($line_items[0]->line_total, 10);
        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testMultipleInvoiceItemTotalSimple()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertTrue(is_array($item_calc->getLineItems()));
        $this->assertEquals(count($item_calc->getLineItems()), 2);

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->quantity, $item->quantity);
        $this->assertEquals($line_items[0]->cost, $item->cost);
        $this->assertEquals($line_items[0]->line_total, 10);
        $this->assertEquals($item_calc->getSubTotal(), 20);
    }

    public function testMultipleInvoiceItemsTotalSimpleWithDiscount()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;
        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->quantity, $item->quantity);
        $this->assertEquals($line_items[0]->cost, $item->cost);
        $this->assertEquals($line_items[0]->discount, $item->discount);
        $this->assertEquals($line_items[0]->line_total, 8);
        $this->assertEquals($item_calc->getSubTotal(), 16);
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

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->quantity, $item->quantity);
        $this->assertEquals($line_items[0]->cost, $item->cost);
        $this->assertEquals($line_items[0]->discount, $item->discount);
        $this->assertEquals($line_items[0]->line_total, 8);
        $this->assertEquals($item_calc->getSubTotal(), 8);
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

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->line_total, 7.48);
        $this->assertEquals($item_calc->getSubTotal(), 7.48);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleExcTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = $this->invoice->is_amount_discount;
        $item->discount = 2;
        $item->tax_rate1 = 10;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $line_items = $item_calc->getLineItems();

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

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleExcTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 2.20);
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

        $this->assertEquals($item_calc->getTotalTaxes(), 2.06);
        $this->assertEquals($item_calc->getGroupedTaxes()->count(), 2);
    }

    public function testInvoiceLevelDiscountIsAmountDiscountOnSubtotal()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];
        $this->invoice->is_amount_discount = true;
        $this->invoice->discount = 1;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testInvoiceLevelDiscountIsPercentDiscountOnSubtotal()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];
        $this->invoice->is_amount_discount = false;
        $this->invoice->discount = 5;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testMultiItemInvoiceLevelDiscountIsAmountDiscountOnSubtotal()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $line_items[] = $item;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = $line_items;

        $this->invoice->is_amount_discount = true;
        $this->invoice->discount = 1;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->line_total, 10);
        $this->assertEquals($line_items[1]->line_total, 10);
        $this->assertEquals($item_calc->getSubTotal(), 20);
    }

    public function testMultiItemInvoiceLevelDiscountIsPercentDiscountOnSubtotal()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $line_items[] = $item;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = $line_items;

        $this->invoice->is_amount_discount = false;
        $this->invoice->discount = 20;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $line_items = $item_calc->getLineItems();

        $this->assertEquals($line_items[0]->line_total, 10);
        $this->assertEquals($line_items[1]->line_total, 10);
        $this->assertEquals($item_calc->getSubTotal(), 20);
    }
}
