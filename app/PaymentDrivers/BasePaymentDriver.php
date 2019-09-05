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

use App\Models\CompanyGateway;
use Omnipay\Omnipay;


/**
 * Class BasePaymentDriver
 * @package App\PaymentDrivers
 */
class BasePaymentDriver
{

	protected $company_gateway;

	protected $gateway;

    public function __construct(CompanyGateway $company_gateway)
    {
        $this->company_gateway = $company_gateway;
        //$this->invitation = $invitation;
        //$this->gatewayType = $gatewayType ?: $this->gatewayTypes()[0];
    }

	/**
	 * Returns the Omnipay driver
	 * @return object Omnipay initialized object
	 */
	protected function gateway()
    {
        if ($this->gateway) 
            return $this->gateway;
        
        $this->gateway = Omnipay::create($this->company_gateway->gateway->provider);
        $this->gateway->initialize((array) $this->company_gateway->getConfig());

        return $this->gateway;
    
	}

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
	 * Refunds a given payment
	 * @return void 
	 */
	public function refundPayment() {}
}