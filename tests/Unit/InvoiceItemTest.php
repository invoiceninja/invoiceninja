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
 * @test
 * @covers  App\Helpers\Invoice\InvoiceItemSum
 */
class InvoiceItemTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
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

        $settings = new \stdClass;
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 2.06);
        $this->assertEquals($item_calc->getGroupedTaxes()->count(), 2);
    }
}
