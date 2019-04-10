<?php

namespace Tests\Unit;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Helpers\Invoice\InvoiceCalc
 */
class InvoiceTest extends TestCase
{

	protected $invoice;

	protected $invoice_calc;

	private $settings;

    public function setUp()
    {
    
    parent::setUp();
	
		$this->invoice = InvoiceFactory::create();
		$this->invoice->line_items = $this->buildLineItems();

		$this->settings = $this->buildSettings();

		$this->invoice_calc = new InvoiceCalc($this->invoice, $this->settings);
	}


	private function buildSettings()
	{
		$settings = new \stdClass;
		$settings->custom_taxes1 = true;
		$settings->custom_taxes2 = true;
		$settings->inclusive_taxes = true;

		return $settings;
	}

	private function buildLineItems()
	{
		$line_items = [];

		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;

		$line_items[] = $item;

		$item = InvoiceItemFactory::create();
		$item->qty = 1;
		$item->cost =10;

		$line_items[] = $item;

		return $line_items;

	}
}