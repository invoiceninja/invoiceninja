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

		$inclusive_tax = true

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

		$inclusive_tax = true

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

		$inclusive_tax = true

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getLineTotal(), 7.48);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecision()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;

		$inclusive_tax = true

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getLineTotal(), 7.48);
	}

	public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleTax()
	{
		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;
		$item->is_amount_discount = true;
		$item->discount = 2.521254522145214511;

		$inclusive_tax = true

		$item_calc = new InvoiceItemCalc($item, 2, $inclusive_tax);
		$item_calc->process();

		$this->assertEquals($item_calc->getLineTotal(), 7.48);
	}

}
