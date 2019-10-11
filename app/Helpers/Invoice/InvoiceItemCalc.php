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
use Illuminate\Support\Facades\Log;

class InvoiceItemCalc
{

	use NumberFormatter;

	protected $item;

	protected $settings;

	protected $invoice;

	private $total_taxes;

	private $total_discounts;

	private $tax_collection;

	private $line_total;

	public function __construct(\stdClass $item, $settings, $invoice)
	{

		$this->item = $item;

		$this->settings = $settings;

		$this->tax_collection = collect([]);

		$this->invoice = $invoice;

		$this->currency = $invoice->client->currency();
	}

	public function process()
	{

		$this->setLineTotal($this->formatValue($this->item->cost, $this->currency->precision) * $this->formatValue($this->item->quantity, $this->currency->precision));

		$this->setDiscount()
		->calcTaxes();

	}	

	private function setDiscount()
	{

		if(!isset($this->item->is_amount_discount))
			return $this;

		if($this->item->is_amount_discount)
		{	

			$discountedTotal = $this->getLineTotal() - $this->formatValue($this->item->discount, $this->currency->precision);

		}
		else
		{ 

			$discountedTotal = $this->getLineTotal() - $this->formatValue(($this->getLineTotal() * $this->item->discount / 100), $this->currency->precision);

		}

	    $this->setLineTotal($discountedTotal);

	    $totalDiscount = $this->getTotalDiscounts() + $discountedTotal;

	    $this->setTotalDiscounts($totalDiscount);

		return $this;
        
	}

	private function calcTaxes()
	{
		$item_tax = 0;

		if(isset($this->item->tax_rate1) && $this->item->tax_rate1 > 0)
		{
			$tax_rate1 = $this->formatValue($this->item->tax_rate1, $this->currency->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate1_total = $this->formatValue(($this->getLineTotal() - ($this->getLineTotal() / (1+$tax_rate1/100))) , $this->currency->precision);
			else
				$item_tax_rate1_total = $this->formatValue(($this->getLineTotal() * $tax_rate1/100), $this->currency->precision);

			$item_tax += $item_tax_rate1_total;

			$this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
		}

		if(isset($this->item->tax_rate2) && $this->item->tax_rate2 > 0)
		{
			$tax_rate2 = $this->formatValue($this->item->tax_rate2, $this->currency->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate2_total = $this->formatValue(($this->getLineTotal() - ($this->getLineTotal() / (1+$tax_rate2/100))) , $this->currency->precision);
			else
				$item_tax_rate2_total = $this->formatValue(($this->getLineTotal() * $tax_rate2/100), $this->currency->precision);

			$item_tax += $item_tax_rate2_total;

			$this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total);


		}

		$this->setTotalTaxes($item_tax);

		return $this;
	}

	private function groupTax($tax_name, $tax_rate, $tax_total) 
	{
		$group_tax = [];

		$key = str_replace(" ", "", $tax_name.$tax_rate);

		$group_tax = ['key' => $key, 'total' => $tax_total, 'tax_name' => $tax_name . ' ' . $tax_rate.'%']; 

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