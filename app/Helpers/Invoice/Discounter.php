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

		if($is_amount_discount){
			return $discount;
		}
		else {
			return round($amount * ($discount / 100), 2);
		}
	}

}
