<?php

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
class InvoiceItemV2Test extends TestCase
{
	
	use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
    
    	parent::setUp();
	
		$this->makeTestData();
		
	}

	public function testInvoiceItemTotalSimple()
	{
		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;
		$item->is_amount_discount = true;

		$settings = new \stdClass;
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

//		$this->assertEquals($item_calc->getLineTotal(), 10);
	}

	// public function testInvoiceItemTotalSimpleWithDiscount()
	// {
	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->is_amount_discount = true;
	// 	$item->discount = 2;

	// 	$settings = new \stdClass;
	// 	$settings->inclusive_taxes = true;
	// 	$settings->precision = 2;
	// 	$item_calc = new InvoiceItemSum($this->invoice, $settings);
	// 	$item_calc->process();

	// 	$this->assertEquals($item_calc->getLineTotal(), 8);
	// }

	// public function testInvoiceItemTotalSimpleWithDiscountWithPrecision()
	// {
	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->is_amount_discount = true;
	// 	$item->discount = 2.521254522145214511;

	// 	$settings = new \stdClass;
	// 	$settings->inclusive_taxes = true;
	// 	$settings->precision = 2;

	// 	$item_calc = new InvoiceItemSum($this->invoice, $settings);
	// 	$item_calc->process();

	// 	$this->assertEquals($item_calc->getLineTotal(), 7.48);
	// }

	// public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax()
	// {
	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->is_amount_discount = true;
	// 	$item->discount = 2.521254522145214511;
	// 	$item->tax_rate1 = 10;

	// 	$settings = new \stdClass;
	// 	$settings->inclusive_taxes = true;
	// 	$settings->precision = 2;

	// 	$item_calc = new InvoiceItemSum($this->invoice, $settings);
	// 	$item_calc->process();

	// 	$this->assertEquals($item_calc->getTotalTaxes(), 0.68);
	// }

	// public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleExclusiveTax()
	// {
	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->is_amount_discount = true;
	// 	$item->discount = 2.521254522145214511;
	// 	$item->tax_rate1 = 10;

	// 	$settings = new \stdClass;
	// 	$settings->inclusive_taxes = false;
	// 	$settings->precision = 2;

	// 	$item_calc = new InvoiceItemSum($this->invoice, $settings);
	// 	$item_calc->process();

	// 	$this->assertEquals($item_calc->getTotalTaxes(), 0.75);
	// }

	// public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleInclusiveTax()
	// {
	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->is_amount_discount = true;
	// 	$item->discount = 2.521254522145214511;
	// 	$item->tax_rate1 = 10;
	// 	$item->tax_rate2 = 17.5;

	// 	$settings = new \stdClass;
	// 	$settings->inclusive_taxes = true;
	// 	$settings->precision = 2;

	// 	$item_calc = new InvoiceItemSum($this->invoice, $settings);
	// 	$item_calc->process();

	// 	$this->assertEquals($item_calc->getTotalTaxes(), 1.79);
	// }

	// public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleExclusiveTax()
	// {
	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->is_amount_discount = true;
	// 	$item->discount = 2.521254522145214511;
	// 	$item->tax_rate1 = 10;
	// 	$item->tax_rate2 = 17.5;

	// 	$settings = new \stdClass;
	// 	$settings->inclusive_taxes = false;
	// 	$settings->precision = 2;

	// 	$item_calc = new InvoiceItemSum($this->invoice, $settings);
	// 	$item_calc->process();

	// 	$this->assertEquals($item_calc->getTotalTaxes(), 2.06);
	// 	$this->assertEquals($item_calc->getGroupedTaxes()->count(), 2);
	// }

}


