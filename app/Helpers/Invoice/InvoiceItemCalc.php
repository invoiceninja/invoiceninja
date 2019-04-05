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

	protected $total_dicounts;

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

			$this->setLineTotal($this->formatValue($this->item->cost, $this->precision) * $this->formatValue($this->item->qty, $this->precision))
			->setDiscount()
			->calcTaxes();

	}	

	private function setDiscount()
	{

		if($this->item->is_amount_discount)
		{	
			$discount = $this->formatValue($this->item->discount, $this->precision);

		    $this->setLineTotal($this->getLineTotal() - $discount);

		    $this->setTotalDiscounts($this->getTotalDiscounts() + $discount);
		}
		else
		{ 
			$discount = $this->formatValue(($this->getLineTotal() * $this->item->discount / 100), $this->precision);

			$this->setLineTotal($this->getLineTotal() - $discount);

		    $this->setTotalDiscounts($this->getTotalDiscounts() + $discount);

		}

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
				$item_tax_rate1_total = $this->formatValue(($this->getLineTotal() * $tax_rate1/100), $this->precision);

			$item_tax += $item_tax_rate1_total;

			$this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
		}

		$tax_rate2 = $this->formatValue($this->item->tax_rate2, $this->precision);

		if($tax_rate2 != 0)
		{
			if($this->inclusive_tax)
				$item_tax_rate2_total = $this->formatValue(($this->getLineTotal() - ($this->getLineTotal() / (1+$tax_rate2/100))) , $this->precision);
			else
				$item_tax_rate2_total = $this->formatValue(($this->getLineTotal() * $tax_rate2/100), $this->precision);

			$item_tax += $item_tax_rate2_total;

			$this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total);


		}

		$this->setTotalTaxes($item_tax);
	}

	private function groupTax($tax_name, $tax_rate, $tax_total) 
	{
		$group_tax = [];

		$key = str_replace(" ", "", $tax_name.$tax_rate);

		$group_tax[$key] = ['total' => $tax_total, 'tax_name' => $tax_name . ' ' . $tax_rate]; 

		$this->setGroupedTaxes($group_tax);
		
	}

	/****************
	*
	* Getters and Setters
	*
	*
	*/
	public function getLimeItem()
	{

		return $this->item;

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

	public function getTotalDiscounts()
	{
		return $this->total_dicounts;
	}

	public function setTotalDiscounts($total)
	{
		$this->total_dicounts = $total;

		return $this;
	}

	public function getGroupedTaxes()
	{
		return $this->tax_collection;
	}

	public function setGroupedTaxes($group_tax)
	{
		$this->tax_collection->merge(collect($group_tax));

		return $this;
	}


}