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

use App\Helpers\Invoice\Balancer;
use App\Helpers\Invoice\CustomValuer;
use App\Helpers\Invoice\Discounter;
use App\Helpers\Invoice\InvoiceItemCalc;
use App\Helpers\Invoice\Taxer;
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
	use Discounter;
	use Balancer;
	use CustomValuer;
	use Taxer;

	protected $invoice;

	protected $settings;

	private $line_items;

	private $item_discount;

	private $balance;

	private $paid_to_date;

	private $amount;

	private $sub_total;

	private $total;

	private $tax_map;

	private $total_item_taxes;

	private $total_taxes;

	private $total_tax_map;

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
		//\Log::error(var_dump($this->settings));

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

        $this->setTotalDiscount($this->discount($this->getSubTotal(), $this->invoice->discount, $this->invoice->is_amount_discount));

      	$this->setTotal( $this->getTotal() - $this->getTotalDiscount() );

      	/* Reduce all taxes */

        return $this;
	}

	
	/**
	 * Calculates the balance.
	 * 
	 * @return     self  The balance.
	 */
	private function calcBalance()
	{

		$this->setBalance($this->balance($this->getTotal(), $this->invoice));

		return $this;

	}


	/**
	 * Calculates the custom values.
	 *
	 * @return     self  The custom values.
	 */
	private function calcCustomValues()
	{

        $this->setTotal($this->getTotal() + $this->valuer($this->invoice->custom_value1, $this->settings->custom_invoice_taxes1));
        
        $this->setTotal($this->getTotal() + $this->valuer($this->invoice->custom_value2, $this->settings->custom_invoice_taxes2));
        
        $this->calcTaxes();

        // custom fields not charged taxes
        $this->setTotal($this->getTotal() + $this->valuer($this->invoice->custom_value1, $this->settings->custom_invoice_taxes1));
        
        $this->setTotal($this->getTotal() + $this->valuer($this->invoice->custom_value2, $this->settings->custom_invoice_taxes2));

        return $this;
	}

	/**
	 * Calculates the Invoice Level taxes.
	 */
	private function calcTaxes()
	{

            $tmp_array = [];

            $taxAmount1 = $this->taxer($this->getSubTotal(), $this->invoice->tax_rate1);
\Log::error("tax 1 pre discount of  ". $this->getSubTotal(). " = ". $taxAmount1);
            $taxAmount1 -= $this->discount($taxAmount1, $this->invoice->discount, $this->invoice->is_amount_discount); 
\Log::error("tax 1 post discount =  ". $this->getSubTotal(). " = ". $taxAmount1);

            if($taxAmount1 > 0)
            	$tmp_array[] = ['name' => $this->invoice->tax_name1 . ' ' . $this->invoice->tax_rate1.'%', 'total' => $taxAmount1];

            $taxAmount2 = $this->taxer($this->getSubTotal(), $this->invoice->tax_rate2);
\Log::error("tax 2 pre discount =  ". $this->getSubTotal(). " = ". $taxAmount2);
            $taxAmount2 -= $this->discount($taxAmount2, $this->invoice->discount, $this->invoice->is_amount_discount); 
\Log::error("tax 2 post discount =  ". $this->getSubTotal(). " = ". $taxAmount2);

            if($taxAmount2 > 0)
            	$tmp_array[] = ['name' => $this->invoice->tax_name2 . ' ' . $this->invoice->tax_rate2.'%', 'total' => $taxAmount2];

            $taxAmount3 = $this->taxer($this->getSubTotal(), $this->invoice->tax_rate3);
\Log::error("tax 3 pre discount =  ". $this->getSubTotal(). " = ". $taxAmount3);
            $taxAmount3 -= $this->discount($taxAmount3, $this->invoice->discount, $this->invoice->is_amount_discount); 
\Log::error("tax 3 post discount =  ". $this->getSubTotal(). " = ". $taxAmount3);

            if($taxAmount3 > 0)
            	$tmp_array[] = ['name' => $this->invoice->tax_name3 . ' ' . $this->invoice->tax_rate3.'%', 'total' => $taxAmount3];

            $this->setTotalTaxMap($tmp_array);

        if ($this->settings->inclusive_taxes === false) {

            $this->setItemTotalTaxes($this->getItemTotalTaxes() + $taxAmount1 + $taxAmount2 + $taxAmount3);

            $this->setTotal($this->getTotal() + $this->getItemTotalTaxes());
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
		//if(!$this->invoice->line_items || !property_exists($this->invoice, 'line_items') || count($this->invoice->line_items) == 0)
		if(!$this->invoice->line_items || count($this->invoice->line_items) == 0)
			return $this;

		$new_line_items = [];

		foreach($this->invoice->line_items as $item) {

			$item_calc = new InvoiceItemCalc($item, $this->settings, $this->invoice);
			$item_calc->process();

			$new_line_items[] = $item_calc->getLineItem();

			//set collection of itemised taxes
			$this->tax_map->push($item_calc->getGroupedTaxes());

			//set running total of taxes
			$this->total_item_taxes += $item_calc->getTotalTaxes();

			$this->setItemTotalTaxes($this->getItemTotalTaxes() + ($item_calc->getTotalTaxes() - $this->discount($item_calc->getTotalTaxes(), $this->invoice->discount, $this->invoice->is_amount_discount)));

			//set running total of item discounts
			$this->item_discount += $item_calc->getTotalDiscounts();

			//set running subtotal
			$this->setSubTotal($this->getSubTotal() + $item_calc->getLineTotal());

			$this->setTotal($this->getTotal() + $item_calc->getLineTotal());

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
	
	public function getTotalTaxMap()
	{
		return $this->total_tax_map;
	}

	public function setTotalTaxMap($value)
	{
		$this->total_tax_map = $value;

		return $this;
	}

	/**
	 * Sums and reduces the line item taxes 
	 * 
	 * @return array The array of tax names and tax totals
	 */
	public function getTaxMap()
	{

        $keys = $this->tax_map->collapse()->pluck('key')->unique();

        $values = $this->tax_map->collapse();

        $tax_array = [];

        foreach($keys as $key)
        {

            $tax_name = $values->filter(function ($value, $k) use($key){
                return $value['key'] == $key;
            })->pluck('tax_name')->first();

            $total_line_tax = $values->filter(function ($value, $k) use($key){
                return $value['key'] == $key;
            })->sum('total');

            $tax_array[] = ['name' => $tax_name, 'total' => $total_line_tax];

        }

        return $tax_array;
    

	}

	private function adjustTaxesWithDiscount($line_taxes)
	{\Log::error($line_taxes);
		return $line_taxes->transform(function($line_tax){
			\Log::error($line_tax['tax_name'] . " " . $line_tax['total']. " ". $this->discount($line_tax['total'], $this->invoice->discount, $this->invoice->is_amount_discount));
			return $line_tax['total'] -= $this->discount($line_tax['total'], $this->invoice->discount, $this->invoice->is_amount_discount);
		});
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

	public function getItemTotalTaxes()
	{
		return $this->total_taxes;
	}

	public function setItemTotalTaxes($value)
	{
		$this->total_taxes = $value;

		return $this;
	}

	public function getTotalLineTaxes()
	{

	}

	public function getTotal()
	{
		return $this->total;
	}

	public function setTotal($value)
	{
		\Log::error($this->total . " sets to  " . $value);

		$this->total = $value;

		return $this;
	}

	public function getBalance()
	{
		return $this->balance;
	}

	public function setBalance($value)
	{
		$this->balance = $value;

		return $this;
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