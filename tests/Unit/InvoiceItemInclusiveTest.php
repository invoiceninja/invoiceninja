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
use App\Helpers\Invoice\InvoiceItemSumInclusive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Helpers\Invoice\InvoiceItemSumInclusive
 */
class InvoiceItemInclusiveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceItemTotalSimpleX()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 10);
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

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 8);
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

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 7.48);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 0;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.91);
        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax2()
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

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.73);
        $this->assertEquals($item_calc->getSubTotal(), 8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 0;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 2.4);
        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithDoubleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 1;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getSubTotal(), 9);
        $this->assertEquals($item_calc->getTotalTaxes(), 2.16);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithDoubleInclusiveTaxMultiQuantity()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 1;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getSubTotal(), 19);
        $this->assertEquals($item_calc->getTotalTaxes(), 4.56);
    }

    public function testInvoiceItemTotalSimpleWithPercentDiscountWithDoubleInclusiveTaxMultiQuantity()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 10;
        $item->is_amount_discount = false;
        $item->discount = 1;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];
        $this->invoice->is_amount_discount = false;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getSubTotal(), 19.8);
        $this->assertEquals($item_calc->getTotalTaxes(), 4.75);
    }
}
