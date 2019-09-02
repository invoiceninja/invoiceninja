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

use App\Helpers\Invoice\InvoiceItemCalc;
use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
	public function __construct($invoice, $settings)
	{
		
		$this->invoice = $invoice;
		$this->settings = $settings;
	
		$this->tax_map = new Collection;

	}
	
	/**
	 * Builds the invoice values
	 */
	public function build()
	{
		//Log::error(print_r($this->invoice,1));

		$this->calcLineItems()
			->calcDiscount()
			->calcCustomValues()
			->calcBalance()
			->calcPartial();

		return $this;
	}


	/**
	 * Calculates the partial balance.
	 *
	 * @return     self  The partial.
	 */
	private function calcPartial()
	{
		if ( !isset($this->invoice->id) && isset($this->invoice->partial) ) {
            $this->invoice->partial = max(0, min($this->formatValue($this->invoice->partial, 2), $this->invoice->balance));
        }

        return $this;
	}


	/**
	 * Calculates the discount.
	 *
	 * @return     self  The discount.
	 */
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


	/**
	 * Calculates the custom values.
	 *
	 * @return     self  The custom values.
	 */
	private function calcCustomValues()
	{

		// custom fields charged taxes
        if (isset($this->invoice->custom_value1) && isset($this->settings->custom_taxes1)) {
            $this->total += $this->invoice->custom_value1;
        }
        if (isset($this->invoice->custom_value2) && isset($this->settings->custom_taxes2)) {
            $this->total += $this->invoice->custom_value2;
        }

        $this->calcTaxes();

        // custom fields not charged taxes
        if (isset($this->invoice->custom_value1) && ! $this->settings->custom_taxes1) {
            $this->total += $this->invoice->custom_value1;
        }
        if (isset($this->invoice->custom_value2) && ! $this->settings->custom_taxes2) {
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
		if(!$this->invoice->line_items || count($this->invoice->line_items) == 0)
			return $this;

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
	
	public function getSubTotal()
	{
		return $this->sub_total;
	}

	public function setSubTotal($value)
	{
		$this->sub_total = $value;

		return $this;
	}
	
	public function getTaxMap()
	{
		return $this->tax_map;
	}

	public function setTaxMap($value)
	{
		$htis->tax_map = $value;

		return $this;
	}

	public function getTotalDiscount()
	{
		return $this->total_discount;
	}

	public function setTotalDiscount($value)
	{
		$this->total_discount = $value;

		return $this;
	}

	public function getTotalTaxes()
	{
		return $this->total_taxes;
	}

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

	public function getInvoice()
	{
		//Build invoice values here and return Invoice
		$this->setCalculatedAttributes();

		return $this->invoice;
	}


	/**
	 * Build $this->invoice variables after
	 * calculations have been performed.
	 */
	private function setCalculatedAttributes()
	{
		/* If amount != balance then some money has been paid on the invoice, need to subtract this difference from the total to set the new balance */
		if($this->invoice->amount != $this->invoice->balance)
		{
			$paid_to_date = $this->invoice->amount - $this->invoice->balance;

			$this->invoice->balance = $this->getTotal() - $paid_to_date;
		}
		else
			$this->invoice->balance = $this->getTotal();

		/* Set new calculated total */
		$this->invoice->amount = $this->getTotal();

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