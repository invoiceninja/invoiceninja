<?php

namespace App\Helpers\Invoice;

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

	protected $precision;

	protected $invoice_total;


	public function __construct(Invoice $invoice, int $precision = 2)
	{
		$this->invoice = $invoice;
		$this->precision = $precision;
	}
	
	public function build()
	{
		$this->calcLineItems();
	}


	private function calcLineItems()
	{

		$new_line_items = [];

		foreach($this->invoice->line_items as $item) {

			$total = $this->formatValue($item->cost) * $this->formatValue($item->qty);
			$total = $this->setDiscount($total, $item->discount, $item->is_amount_discount);
			$total = $this->setTaxRate($total, $item->tax_name1, $item->tax_rate1);
			
			$item->line_total = $total;
			
			$new_line_items[] = $item;

			$this->setInvoiceTotal($total);
		}

		$this->invoice->line_items = $new_line_items;

	}


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






}