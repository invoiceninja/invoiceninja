<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Helpers\Invoice;

use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Support\Collection;

class InvoiceItemCalc
{

	use NumberFormatter;

	protected $item;

	protected $settings;

	private $total_taxes;

	private $total_discounts;

	private $tax_collection;

	private $line_total;

	public function __construct(\stdClass $item, $settings)
	{

		$this->item = $item;

		$this->settings = $settings;

		$this->tax_collection = collect([]);

	}

	public function process()
	{
		$this->line_total = $this->formatValue($this->item->cost, $this->settings->precision) * $this->formatValue($this->item->quantity, $this->settings->precision);

		$this->setDiscount()
		->calcTaxes();

	}	

	private function setDiscount()
	{

		if(!isset($this->item->is_amount_discount))
			return $this;

		if($this->item->is_amount_discount)
		{	
			$discount = $this->formatValue($this->item->discount, $this->settings->precision);

		    $this->line_total -= $discount;

		    $this->total_discounts += $discount;
		}
		else
		{ 
			$discount = $this->formatValue(($this->line_total * $this->item->discount / 100), $this->settings->precision);

		    $this->line_total -= $discount;

		    $this->total_discounts += $discount;

		}

		return $this;
        
	}

	private function calcTaxes()
	{
		$item_tax = 0;

		if(isset($this->item->tax_rate1) && $this->item->tax_rate1 > 0)
		{
			$tax_rate1 = $this->formatValue($this->item->tax_rate1, $this->settings->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate1_total = $this->formatValue(($this->line_total - ($this->line_total / (1+$tax_rate1/100))) , $this->settings->precision);
			else
				$item_tax_rate1_total = $this->formatValue(($this->line_total * $tax_rate1/100), $this->settings->precision);

			$item_tax += $item_tax_rate1_total;

			$this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
		}

		if(isset($this->item->tax_rate2) && $this->item->tax_rate2 > 0)
		{
			$tax_rate2 = $this->formatValue($this->item->tax_rate2, $this->settings->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate2_total = $this->formatValue(($this->line_total - ($this->line_total / (1+$tax_rate2/100))) , $this->settings->precision);
			else
				$item_tax_rate2_total = $this->formatValue(($this->line_total * $tax_rate2/100), $this->settings->precision);

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

		$this->tax_collection->push(collect($group_tax));
		
	}

	/****************
	*
	* Getters and Setters
	*
	*
	*/
	public function getLineItem()
	{

		return $this->item;

	}

	public function getLineTotal()
	{
		return $this->line_total;
	}

	public function setLineTotal($total)
	{
		$this->line_total = $total;

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
		return $this->total_discounts;
	}

	public function setTotalDiscounts($total)
	{
		$this->total_discounts = $total;

		return $this;
	}

	public function getGroupedTaxes()
	{
		return $this->tax_collection;
	}

	public function setGroupedTaxes($group_taxes)
	{
		$this->tax_collection = $group_taxes;

		return $this;
	}


}