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
 * Class for discount calculations
 */
trait Discounter
{

	public function discount($amount, $discount, $is_amount_discount)
	{
		\Log::error("{$amount}, {$discount}, {$is_amount_discount}");

		if($is_amount_discount === true)
			return $discount;

		
		return round($amount * ($discount / 100), 2);
		
	}

	public function pro_rata_discount($amount, $total, $discount, $is_amount_discount)
	{
		if($is_amount_discount === true){
			return round(($discount/$total * $amount),4);
		}

		
		return round($amount * ($discount / 100), 2);
		
	}

}
