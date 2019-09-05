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


/**
 * Class BasePaymentDriver
 * @package App\PaymentDrivers
 */
abstract class BasePaymentDriver
{

	/**
	 * Returns whether refunds are possible with the gateway
	 * @return boolean TRUE|FALSE
	 */
	public function isRefundable() :bool {}

	/**
	 * Returns whether token billing is possible with the gateway
	 * @return boolean TRUE|FALSE
	 */
	public function hasTokenBilling() :bool {}

	/**
	 * Returns the Omnipay driver
	 * @return object Omnipay initialized object
	 */
	public function gateway() {}
	
}