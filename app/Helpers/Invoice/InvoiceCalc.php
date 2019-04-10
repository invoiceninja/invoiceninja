<?php

namespace App\Helpers\Invoice;

use App\Helpers\Invoice\InvoiceItemCalc;
use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;

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

	private $total_taxes;

	private $total_discount;


	/**
	 * Constructs the object with Invoice and Settings object
	 *
	 * @param      \App\Models\Invoice  $invoice   The invoice
	 * @param      \stdClass            $settings  The settings
	 */
	public function __construct(Invoice $invoice, \stdClass $settings)
	{
		$this->invoice = $invoice;
		$this->settings = $settings;
	}
	
	/**
	 * Builds the invoice values
	 */
	public function build()
	{
		$this->calcLineItems()
			->calcDiscount()
			->calcCustomValues()
			->calcBalance()
			->calcPartial();

		return $this;
	}

	private function calcPartial()
	{
		if ( !$this->invoice->id && isset($this->invoice->partial) ) {
            $this->invoice->partial = max(0, min($this->formatValue($this->invoice->partial, 2), $this->invoice->balance));
        }
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
            $this->invoice->balance = round($this->total - ($this->invoice->amount - $this->invoice->balance), 2);
        } else {
            $this->invoice->balance = $this->total;
        }

		return $this;

	}

	private function calcCustomValues()
	{
		$this->total += $this->getSubTotal();

		// custom fields charged taxes
        if ($this->invoice->custom_value1 && $this->settings->custom_taxes1) {
            $this->total += $invoice->custom_value1;
        }
        if ($invoice->custom_value2 && $invoice->custom_taxes2) {
            $this->total += $invoice->custom_value2;
        }

        $this->calcTaxes();

        // custom fields not charged taxes
        if ($invoice->custom_value1 && ! $this->settings->custom_taxes1) {
            $this->total += $invoice->custom_value1;
        }
        if ($invoice->custom_value2 && ! $this->settings->custom_taxes2) {
            $this->total += $invoice->custom_value2;
        }
	}

	/**
	 * Calculates the Invoice Level taxes.
	 */
	private function calcTaxes()
	{

        if (! $this->settings->inclusive_taxes) {
            $taxAmount1 = round($this->total * ($this->invoice->tax_rate1 ? $this->invoice->tax_rate1 : 0) / 100, 2);
            $taxAmount2 = round($this->total * ($this->invoice->tax_rate2 ? $this->invoice->tax_rate2 : 0) / 100, 2);
            $this->total = round($this->total + $taxAmount1 + $taxAmount2, 2);
            $this->total += $this->total_taxes;
        }

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

			$item_calc = new InvoiceItemCalc($item);
			$item_calc->process();


			$new_line_items[] = $item_calc->getLineItem();

			//set collection of itemised taxes
			$this->tax_map->merge($item_calc->getGroupedTaxes());

			//set running total of taxes
			$this->total_taxes += $item_calc->getTotalTaxes();

			//set running total of discounts
			$this->total_discount += $item_calc->getTotalDiscounts();

			//set running subtotal
			$this->sub_total += $item_calc->getLineTotal();
						
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
	private function getSubTotal()
	{
		return $this->subtotal;
	}

	/**
	 * Sets the sub total.
	 *
	 * @param      float  $value  The value
	 *
	 * @return     self    $this
	 */
	private function setSubTotal($value)
	{
		$this->sub_total = $value;

		return $this;
	}
	
	/**
	 * Gets the tax map.
	 *
	 * @return     Collection  The tax map.
	 */
	private function getTaxMap()
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
	private function setTaxMap($value)
	{
		$htis->tax_map = $value;

		return $this;
	}

	/**
	 * Gets the total discount.
	 *
	 * @return     float  The total discount.
	 */
	private function getTotalDiscount()
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
	private function setTotalDiscount($value)
	{
		$this->total_discount = $value;

		return $this;
	}

	/**
	 * Gets the total taxes.
	 *
	 * @return     float  The total taxes.
	 */
	private function getTotalTaxes()
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