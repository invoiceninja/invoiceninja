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

use App\Helpers\Invoice\Discounter;
use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Support\Collection;

class InvoiceItemSum
{

	use NumberFormatter;
	use Discounter;

	protected $settings;

	protected $invoice;

	private $items;

	private $line_total;

	private $currency;

	private $total_taxes;

	private $item;

	private $line_items;

	private $sub_total;

	private $total_discount;

	private $tax_collection;

	public function __construct($invoice, $settings)
	{

		$this->settings = $settings;

		$this->tax_collection = collect([]);

		$this->invoice = $invoice;

		$this->currency = $invoice->client->currency();

		$this->line_items = [];
	}

	public function process()
	{
		if(!$this->invoice->line_items || count($this->invoice->line_items) == 0){
			$this->items = [];
			return $this;
		}

		$this->buildLineItems();

		return $this;
	}

	private function buildLineItems()
	{
		foreach($this->invoice->line_items as $this->item)
		{
			$this->sumLineItem()
				->setDiscount()
				->calcTaxes()
				->push();
		}

		return $this;
	}

	private function push()
	{

		$this->sub_total += $this->getLineTotal();

		$this->line_items[] = $this->item;

		return $this;
	}

	private function sumLineItem()
	{
		$this->setLineTotal($this->formatValue($this->item->cost, $this->currency->precision) * $this->formatValue($this->item->quantity, $this->currency->precision));
		return $this;
	}

	private function setDiscount()
	{

		if($this->item->is_amount_discount)
		{	
			$this->setLineTotal($this->getLineTotal() - $this->formatValue($this->item->discount, $this->currency->precision));
		}
		else
		{
			$this->setLineTotal($this->getLineTotal() - $this->formatValue(round($this->item->line_total * ($this->item->discount / 100),2), $this->currency->precision));
		}

		return $this;
        
	}

	private function calcTaxes()
	{
		$item_tax = 0;

		if(isset($this->item->tax_rate1) && $this->item->tax_rate1 > 0)
		{
			$tax_rate1 = $this->formatValue($this->item->tax_rate1, $this->currency->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate1_total = $this->formatValue(($this->item->line_total - ($this->item->line_total / (1+$tax_rate1/100))) , $this->currency->precision);
			else
				$item_tax_rate1_total = $this->formatValue(($this->item->line_total * $tax_rate1/100), $this->currency->precision);

			$item_tax += $item_tax_rate1_total;

			$this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
		}

		if(isset($this->item->tax_rate2) && $this->item->tax_rate2 > 0)
		{
			$tax_rate2 = $this->formatValue($this->item->tax_rate2, $this->currency->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate2_total = $this->formatValue(($this->item->line_total - ($this->item->line_total / (1+$tax_rate2/100))) , $this->currency->precision);
			else
				$item_tax_rate2_total = $this->formatValue(($this->item->line_total * $tax_rate2/100), $this->currency->precision);

			$item_tax += $item_tax_rate2_total;

			$this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total);

		}

		if(isset($this->item->tax_rate3) && $this->item->tax_rate3 > 0)
		{
			$tax_rate3 = $this->formatValue($this->item->tax_rate3, $this->currency->precision);

			if($this->settings->inclusive_taxes)
				$item_tax_rate3_total = $this->formatValue(($this->item->line_total - ($this->item->line_total / (1+$tax_rate3/100))) , $this->currency->precision);
			else
				$item_tax_rate3_total = $this->formatValue(($this->item->line_total * $tax_rate3/100), $this->currency->precision);

			$item_tax += $item_tax_rate3_total;

			$this->groupTax($this->item->tax_name3, $this->item->tax_rate3, $item_tax_rate3_total);

		}

		//todo if exclusive add on top, if inclusive need to reduce item rates

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

	public function getTotalTaxes()
	{
		return $this->total_taxes;
	}

	public function setTotalTaxes($total)
	{
		$this->total_taxes = $total;

		return $this;
	}

	public function setLineTotal($total)
	{
		$this->item->line_total = $total;

		return $this;
	}

	public function getLineTotal()
	{
		return $this->item->line_total;
	}

	public function getLineItems()
	{
		return $this->line_items;
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

	public function getSubTotal()
	{
		return $this->sub_total;
	}

	public function setSubTotal($value)
	{
		$this->sub_total = $value;
		return $this;
	}

}