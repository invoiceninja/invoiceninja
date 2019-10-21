<?php

namespace Tests\Unit;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Helpers\Invoice\InvoiceSumInclusive
 */
class InvoiceInclusiveTest extends TestCase
{
	use MockAccountData;
	use DatabaseTransactions;

	public $invoice;

	public $invoice_calc;

	public $settings;

    public function setUp() :void
    {
    
    	parent::setUp();
	
		$this->makeTestData();

		$this->invoice->line_items = $this->buildLineItems();
		
		$this->settings = $this->invoice->settings;

		$this->settings->inclusive_taxes = true;
		$this->settings->precision = 2;

		$this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);

	}

	private function buildLineItems()
	{
		$line_items = [];

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;

		$line_items[] = $item;

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;

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
		$this->assertEquals($this->invoice_calc->getTotal(), 15);
		$this->assertEquals($this->invoice_calc->getBalance(), 15);
	}

	public function testInvoiceTotalsWithDiscountWithSurcharge()
	{
		$this->invoice->discount = 5;
		$this->invoice->custom_value1 = 5;
			
		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 20);
		$this->assertEquals($this->invoice_calc->getTotal(), 20);
		$this->assertEquals($this->invoice_calc->getBalance(), 20);
	}

	public function testInvoiceTotalsWithDiscountWithSurchargeWithInclusiveTax()
	{
		$this->invoice->discount = 5;
		$this->invoice->custom_value1 = 5;
		$this->invoice->tax_name1 = 'GST';
		$this->invoice->tax_rate1 = 10;
		$this->invoice->is_amount_discount = true;

		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 18.18);
		$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.32);
		$this->assertEquals($this->invoice_calc->getTotal(), 19.5);
		$this->assertEquals($this->invoice_calc->getBalance(), 19.5);
	}

	public function testInvoiceTotalsWithPercentDiscountWithSurchargeWithInclusiveTax()
	{
		$this->invoice->discount = 5;
		$this->invoice->custom_value1 = 5;
		$this->invoice->tax_name1 = 'GST';
		$this->invoice->tax_rate1 = 10;
		$this->invoice->is_amount_discount = false;

		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 18.18);
		$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.73);
		$this->assertEquals($this->invoice_calc->getTotal(), 24);
	}

	public function testInvoiceTotalsWithDiscountWithSurchargeWithExclusiveTax()
	{

		$this->invoice->discount = 5;
		$this->invoice->custom_value1 = 5;
		$this->invoice->tax_name1 = 'GST';
		$this->invoice->tax_rate1 = 10;
		$this->settings->inclusive_taxes = true;
		$this->invoice->is_amount_discount = true;

		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 18.18);
		$this->assertEquals($this->invoice_calc->getTotal(), 19.5);
		$this->assertEquals($this->invoice_calc->getTotal(), 19.5);
		//$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.5);
	}

	public function testInvoiceTotalsWithDiscountWithSurchargeWithDoubleExclusiveTax()
	{

		$this->invoice->discount = 5;
		$this->invoice->custom_value1 = 5;
		$this->invoice->tax_name1 = 'GST';
		$this->invoice->tax_rate1 = 10;
		$this->invoice->tax_name2 = 'GST';
		$this->invoice->tax_rate2 = 10;
		$this->settings->inclusive_taxes = true;
		$this->invoice->is_amount_discount = true;

		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 16.52);
		$this->assertEquals($this->invoice_calc->getTotal(), 18.82);
		$this->assertEquals($this->invoice_calc->getTotalTaxes(), 2.3);
	}


	public function testLineItemTaxRatesInclusiveTaxes()
	{
		$line_items = [];

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;
		$item->tax_rate1 = 10;
		$item->tax_name1 = 10;

		$line_items[] = $item;

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;
		$item->tax_rate1 = 10;
		$item->tax_name1 = 10;

		$line_items[] = $item;

		$this->invoice->line_items = $line_items;

		$this->settings->inclusive_taxes = true;
		$this->invoice->discount = 0;
		$this->invoice->custom_value1 = 0;

		$this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 18.18);
		$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.82);
		$this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
		$this->assertEquals($this->invoice_calc->getTotal(), 20);
		$this->assertEquals($this->invoice_calc->getBalance(), 20);
	}

	public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxes()
	{
		$line_items = [];

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;
		$item->tax_rate1 = 10;
		$item->tax_name1 = 10;

		$line_items[] = $item;

		$item = InvoiceItemFactory::create();
		$item->quantity = 1;
		$item->cost =10;
		$item->tax_rate1 = 10;
		$item->tax_name1 = 10;

		$line_items[] = $item;

		$this->invoice->line_items = $line_items;

		$this->settings->inclusive_taxes = true;
		$this->invoice->discount = 0;
		$this->invoice->custom_value1 = 0;

		$this->invoice->tax_rate1 = 10;
		$this->invoice->tax_rate2 = 10;

		$this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
		$this->invoice_calc->build();

		$this->assertEquals($this->invoice_calc->getSubTotal(), 15.02);
		$this->assertEquals($this->invoice_calc->getTotalTaxes(), 4.5);
		$this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
		$this->assertEquals($this->invoice_calc->getTotal(), 19.52);
		$this->assertEquals($this->invoice_calc->getBalance(), 19.52);
	}

	// public function testLineItemTaxRatesExclusiveTaxes()
	// {

	// 	$line_items = [];

	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->tax_rate1 = 10;
	// 	$item->tax_name1 = 10;

	// 	$line_items[] = $item;

	// 	$item = InvoiceItemFactory::create();
	// 	$item->quantity = 1;
	// 	$item->cost =10;
	// 	$item->tax_rate1 = 10;
	// 	$item->tax_name1 = 10;

	// 	$line_items[] = $item;

	// 	$this->invoice->line_items = $line_items;
	// 	$this->invoice->discount = 0;
	// 	$this->invoice->tax_name1 = 'GST';
	// 	$this->invoice->tax_rate1 = 10;
	// 	$this->invoice->tax_name2 = 'GST';
	// 	$this->invoice->tax_rate2 = 10;

	// 	$this->settings->inclusive_taxes = false;
	// 	$this->invoice_calc = new InvoiceSum($this->invoice, $this->settings);
	// 	$this->invoice_calc->build();

	// 	$this->assertEquals($this->invoice_calc->getSubTotal(), 20);
	// 	//$this->assertEquals($this->invoice_calc->getTotal(), 26);
	// 	//$this->assertEquals($this->invoice_calc->getBalance(), 26);
	// 	//$this->assertEquals($this->invoice_calc->getTotalTaxes(), 4);
	// 	//$this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
	// }

}