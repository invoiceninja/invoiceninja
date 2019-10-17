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

/**
 * Class for tax calculations
 */
trait Taxer
{

	public function taxer($amount, $tax_rate)
	{
		return round($amount * (($tax_rate ? $tax_rate : 0) / 100), 2);
	}

	public function calcLineTax($tax_rate, $item)
	{
		if(!isset($tax_rate) || $tax_rate == 0)
			return 0;

		if($this->settings->inclusive_taxes)
			return $this->inclusiveTax($tax_rate, $item);

		return $this->exclusiveTax($tax_rate, $item);
	}

	public function exclusiveTax($tax_rate, $item)
	{

		$tax_rate = $this->formatValue($tax_rate, 4);

		return $this->formatValue(($item->line_total * $tax_rate/100), 4);

	}

	public function calcAmountLineTax($tax_rate, $amount)
	{
		return $this->formatValue(($amount * $tax_rate/100), 4);
	}

	public function inclusiveTax($tax_rate, $item)
	{

		$tax_rate = $this->formatValue($tax_rate, 4);

		return $this->formatValue(($item->line_total - ($item->line_total / (1+$tax_rate/100))) , 4);
	}

}
