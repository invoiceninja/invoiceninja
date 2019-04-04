<?php

namespace App\Helpers\Invoice;

use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;

class InvoiceItemCalc
{

	use NumberFormatter;

	protected $item;

	protected $precision;

	protected $inclusive_tax;

	protected $total_taxes;

	protected $tax_collection;

	public function __construct(\stdClass $item, int $precision = 2, bool $inclusive_tax)
	{
		$this->item = $item;
		$this->precision = $precision;
		$this->inclusive_tax = $inclusive_tax;
		$this->tax_collection = collect();
	}

	public function process()
	{
			$this->setLineTotal($this->formatValue($this->item->cost, $this->precision) * $this->formatValue($this->item->qty, $this->precision));
			$this->setDiscount();
			$this->calcTaxes();
			$this->groupTaxes();
	}	

	private function setDiscount()
	{

		if($this->item->is_amount_discount)
		    $this->setLineTotal($this->getLineTotal() - $this->formatValue($this->item->discount, $this->precision));
		else 
			$this->setLineTotal($this->getLineTotal() - $this->formatValue(($this->getLineTotal() * $this->item->discount / 100), $this->precision));

		return $this;
        
	}

	private function calcTaxes()
	{
		$item_tax = 0;

		$tax_rate1 = $this->formatValue($this->item->tax_rate1, $this->precision);

		if($tax_rate1 != 0)
		{
			if($this->inclusive_tax)
				$item_tax_rate1_total = $this->formatValue(($this->getLineTotal() - ($this->getLineTotal() / (1+$tax_rate1/100))) , $this->precision);
			else
				$item_tax_rate1_total += $this->formatValue(($this->getLineTotal() * $tax_rate1/100), $this->precision);

			$item_tax += $item_tax_rate1_total;

			$this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
		}

		$tax_rate2 = $this->formatValue($this->item->tax_rate2, $this->precision);

		if($tax_rate2 != 0)
		{
			if($this->inclusive_tax)
				$item_tax += $this->formatValue(($this->getLineTotal() - ($this->getLineTotal() / (1+$tax_rate2/100))) , $this->precision);
			else
				$item_tax += $this->formatValue(($this->getLineTotal() * $tax_rate2/100), $this->precision);

		}

		$this->setTotalTaxes($item_tax);
	}

	private function groupTax($tax_name, $tax_rate, $tax_total) : array
	{
		$group_tax = [];

		$key = str_replace(" ", "", $tax_name.$tax_rate);
		$group_tax[$key] = $tax_total

		return $group_tax;
		
	}

	private function groupTaxes($group_tax)
	{

		$this->tax_collection->merge(collect($group_tax));

		return $this;
	}

	public function getLineTotal()
	{
		return $this->item->line_total;
	}

	public function setLineTotal($total)
	{
		$this->item->line_total = $total;

		return $this;
	}

	public function getTotalTaxes()
	{
		return $this->total_taxes;
	}

	public function setTotalTaxes($total)
	{
		$this->total_taxes = $total;

		return $this;
	}

}