<?php

namespace App\Helpers\Invoice;

use App\Helpers\Invoice\InvoiceItemCalc;
use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;

class InvoiceCalc
{

	use NumberFormatter;

	protected $invoice;

	protected $balance;

	protected $paid_to_date;

	protected $amount;

	protected $line_items;

	protected $settings;

	protected $invoice_total;

	protected $tax_map;

	protected $total_taxes;

	protected $total_discount;

	public function __construct(Invoice $invoice, \stdClass $settings)
	{
		$this->invoice = $invoice;
		$this->settings = $settings;
	}
	
	public function build()
	{
		$this->calcLineItems();
	}


	private function calcLineItems()
	{

		$new_line_items = [];

		foreach($this->invoice->line_items as $item) {

			$item_calc = new InvoiceItemCalc($item);
			$item_calc->process();


			$new_line_items[] = $item_calc->getLineItem();

			//set collection of itemised taxes
			$this->tax_map->merge($item_calc->getGroupedTaxes());

			//set running total of taxes
			$this->total_taxes += $item_calc->getTotalTaxes();

			//set running total of discounts
			$this->total_discount += $item_calc->getTotalDiscounts();
						
		}

		$this->invoice->line_items = $new_line_items;

		return $this;
	}




	/**
	 * Getters and Setters
	 */
	
	private function getTaxMap()
	{
		return $this->tax_map;
	}

	private function setTaxMap($value)
	{
		$htis->tax_map = $value;

		return $this;
	}

	private function getTotalDiscount()
	{
		return $this->total_discount;
	}

	private function setTotalDiscount($value)
	{
		$this->total_discount = $value;

		return $this;
	}

	private function getTotalTaxes()
	{
		return $this->total_taxes;
	}

	private function setTotalTaxes($value)
	{
		$this->total_taxes = $value;

		return $this;
	}
/*
	private function setDiscount($amount, $discount, $is_amount_discount)
	{

		if($is_amount_discount)
		    return $amount - $this->formatValue($discount);
		else 
			return $amount - $this->formatValue($amount * $discount / 100);
        
	}

	private function getInvoiceTotal()
	{
		return $this->invoice_total;
	}

	private function setInvoiceTotal($invoice_total)
	{
		$this->invoice_total = $invoice_total;
	}

*/




}