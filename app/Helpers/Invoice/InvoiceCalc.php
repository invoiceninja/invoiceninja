<?php

namespace App\Helpers\Invoice;

use App\Helpers\Invoice\InvoiceItemCalc;
use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Support\Collection;

/**
 * Class for invoice calculations.
 */
class InvoiceCalc
{

	use NumberFormatter;

	protected $invoice;

	protected $settings;

	private $line_items;

	private $balance;

	private $paid_to_date;

	private $amount;

	private $sub_total;

	private $total;

	private $tax_map;

	private $total_item_taxes;

	private $total_taxes;

	private $total_discount;


	/**
	 * Constructs the object with Invoice and Settings object
	 *
	 * @param      \App\Models\Invoice  $invoice   The invoice
	 */
	public function __construct($invoice)
	{
		$this->invoice = $invoice;
		$this->settings = $invoice->settings;
		$this->tax_map = new Collection;
	}
	
	/**
	 * Builds the invoice values
	 */
	public function build()
	{
		$this->calcLineItems()
			->calcDiscount()
			->calcCustomValues()
			//->calcTaxes()
			->calcBalance()
			->calcPartial();

		return $this;
	}

	private function calcPartial()
	{
		if ( !isset($this->invoice->id) && isset($this->invoice->partial) ) {
            $this->invoice->partial = max(0, min($this->formatValue($this->invoice->partial, 2), $this->invoice->balance));
        }

        return $this;
	}

	private function calcDiscount()
	{
        if ($this->invoice->discount != 0) {

            if ($this->invoice->is_amount_discount) {

                $this->total -= $this->invoice->discount;

            } else {

                $this->total -= round($this->total * ($this->invoice->discount / 100), 2);

            }

        }

        return $this;
	}

	
	/**
	 * Calculates the balance.
	 * 
	 * //todo need to understand this better
	 *
	 * @return     self  The balance.
	 */
	private function calcBalance()
	{

		if(isset($this->invoice->id) && $this->invoice->id >= 1)
		{
            $this->balance = round($this->total - ($this->invoice->amount - $this->invoice->balance), 2);
        } else {
            $this->balance = $this->total;
        }

		return $this;

	}

	private function calcCustomValues()
	{

		// custom fields charged taxes
        if ($this->invoice->custom_value1 && $this->settings->custom_taxes1) {
            $this->total += $this->invoice->custom_value1;
        }
        if ($this->invoice->custom_value2 && $this->invoice->custom_taxes2) {
            $this->total += $this->invoice->custom_value2;
        }

        $this->calcTaxes();

        // custom fields not charged taxes
        if ($this->invoice->custom_value1 && ! $this->settings->custom_taxes1) {
            $this->total += $this->invoice->custom_value1;
        }
        if ($this->invoice->custom_value2 && ! $this->settings->custom_taxes2) {
            $this->total += $this->invoice->custom_value2;
        }

        return $this;
	}

	/**
	 * Calculates the Invoice Level taxes.
	 */
	private function calcTaxes()
	{

        if (! $this->settings->inclusive_taxes) {
            $taxAmount1 = round($this->total * ($this->invoice->tax_rate1 ? $this->invoice->tax_rate1 : 0) / 100, 2);
            $taxAmount2 = round($this->total * ($this->invoice->tax_rate2 ? $this->invoice->tax_rate2 : 0) / 100, 2);
            $this->total_taxes = round($taxAmount1 + $taxAmount2, 2) + $this->total_item_taxes;
            $this->total += $this->total_taxes;
        }

        return $this;
	}

	/**
	 * Calculates the line items.
	 *
	 * @return   self  The line items.
	 */
	private function calcLineItems()
	{

		$new_line_items = [];

		foreach($this->invoice->line_items as $item) {

			$item_calc = new InvoiceItemCalc($item, $this->settings);
			$item_calc->process();


			$new_line_items[] = $item_calc->getLineItem();

			//set collection of itemised taxes
			$this->tax_map->push($item_calc->getGroupedTaxes());

			//set running total of taxes
			$this->total_item_taxes += $item_calc->getTotalTaxes();

			//set running total of discounts
			$this->total_discount += $item_calc->getTotalDiscounts();

			//set running subtotal
			$this->sub_total += $item_calc->getLineTotal();

			$this->total += $item_calc->getLineTotal();

		}

		$this->invoice->line_items = $new_line_items;

		return $this;
	}


	/**
	 * Getters and Setters
	 */
	

	/**
	 * Gets the sub total.
	 *
	 * @return     float  The sub total.
	 */
	public function getSubTotal()
	{
		return $this->sub_total;
	}

	/**
	 * Sets the sub total.
	 *
	 * @param      float  $value  The value
	 *
	 * @return     self    $this
	 */
	public function setSubTotal($value)
	{
		$this->sub_total = $value;

		return $this;
	}
	
	/**
	 * Gets the tax map.
	 *
	 * @return     Collection  The tax map.
	 */
	public function getTaxMap()
	{
		return $this->tax_map;
	}

	/**
	 * Sets the tax map.
	 *
	 * @param      Collection  $value  Collection of mapped taxes
	 *
	 * @return     self    $this
	 */
	public function setTaxMap($value)
	{
		$htis->tax_map = $value;

		return $this;
	}

	/**
	 * Gets the total discount.
	 *
	 * @return     float  The total discount.
	 */
	public function getTotalDiscount()
	{
		return $this->total_discount;
	}

	/**
	 * Sets the total discount.
	 *
	 * @param      float  $value  The value
	 *
	 * @return     self    $this
	 */
	public function setTotalDiscount($value)
	{
		$this->total_discount = $value;

		return $this;
	}

	/**
	 * Gets the total taxes.
	 *
	 * @return     float  The total taxes.
	 */
	public function getTotalTaxes()
	{
		return $this->total_taxes;
	}

	/**
	 * Sets the total taxes.
	 *
	 * @param      float  $value  The value
	 *
	 * @return     self    ( $this )
	 */
	public function setTotalTaxes($value)
	{
		$this->total_taxes = $value;

		return $this;
	}

	public function getTotal()
	{
		return $this->total;
	}

	public function setTotal($value)
	{
		$this->total = $value;
	}

	public function getBalance()
	{
		return $this->balance;
	}

	public function setBalance($value)
	{
		$this->balance = $value;
	}

/*
	private function setDiscount($amount, $discount, $is_amount_discount)
	{

		if($is_amount_discount)
		    return $amount - $this->formatValue($discount);
		else 
			return $amount - $this->formatValue($amount * $discount / 100);
        
	}



*/




}