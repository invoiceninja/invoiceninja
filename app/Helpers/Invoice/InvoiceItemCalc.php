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

	public function __construct(\stdClass $item, int $precision = 2, bool $inclusive_tax)
	{
		$this->item = $item;
		$this->precision = $precision;
		$this->inclusive_tax = $inclusive_tax;
	}

	public function process()
	{
			$this->setLineTotal($this->formatValue($this->item->cost, $this->precision) * $this->formatValue($this->item->qty, $this->precision));
			$this->setDiscount();
			$this->calcTaxes();
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
			$item_tax += $this->formatValue(($this->getLineTotal() * $tax_rate1/100) , $this->precision);
		}

		$tax_rate2 = $this->formatValue($this->item->tax_rate2, $this->precision);

		if($tax_rate2 != 0)
		{
			$item_tax += $this->formatValue(($this->getLineTotal() * $tax_rate2/100) , $this->precision);
		}

		$this->setTotalTaxes($item_tax);
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