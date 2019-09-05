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

namespace App\PaymentDrivers;

use Stripe\Stripe;

class StripePaymentDriver extends BasePaymentDriver
{

	/**
	 * Methods in this class are divided into
	 * two separate streams
	 * 
	 * 1. Omnipay Specific
	 * 2. Stripe Specific
	 * 
	 * Our Stripe integration is deeper than 
	 * other gateways and therefore
	 * relies on direct calls to the API
	 */



	/************************************** Omnipay API methods **********************************************************/








	/************************************** Stripe API methods **********************************************************/

	public function init($api_key)
	{
        Stripe::setApiKey($api_key);
	}

}