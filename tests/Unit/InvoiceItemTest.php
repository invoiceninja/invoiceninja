<?php

namespace Tests\Unit;

use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceItemCalc;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Helpers\Invoice\InvoiceItemCalc
 */
class InvoiceItemTest extends TestCase
{
    public function setUp()
    {
    
    parent::setUp();
	
	}

	public function testInvoiceItemTotalSimple()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;

		$inclusive_tax = true;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getLineTotal(), 10);
	}

	public function testInvoiceItemTotalSimpleWithDiscount()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2;

		$inclusive_tax = true;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getLineTotal(), 8);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecision()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;

		$inclusive_tax = true;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getLineTotal(), 7.48);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;
		$item->tax_rate1 = 10;

		$inclusive_tax = true;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getTotalTaxes(), 0.68);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleExclusiveTax()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;
		$item->tax_rate1 = 10;

		$inclusive_tax = false;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getTotalTaxes(), 0.75);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleInclusiveTax()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;
		$item->tax_rate1 = 10;
		$item->tax_rate2 = 17.5;

		$inclusive_tax = true;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getTotalTaxes(), 1.79);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleExclusiveTax()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;
		$item->tax_rate1 = 10;
		$item->tax_rate2 = 17.5;

		$inclusive_tax = false;

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getTotalTaxes(), 2.06);
	}

}


