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
trait CustomValuer
{

	public function valuer($custom_value, $has_custom_invoice_taxes1)
	{

		if(isset($custom_value) && $has_custom_invoice_taxes1 === true)
        	return $custom_value;

        return 0;
	}

}

