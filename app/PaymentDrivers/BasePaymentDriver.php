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
	/* The company gateway instance*/
	protected $company_gateway;

	/* The Omnipay payment driver instance*/
	protected $gateway;

	/* Member variables */
	protected $refundable = false;
	protected $token_billing = false;

    public function __construct(CompanyGateway $company_gateway)
    {
        $this->company_gateway = $company_gateway;
        //$this->invitation = $invitation;
        //$this->gatewayType = $gatewayType ?: $this->gatewayTypes()[0];
    }

    /* Stubbed in parent, but should never be initialized
	 * The boot method should be used in the superclass
	 * to initialize all the member variables for the 
	 * given driver / payment gateway
	 *
	 * ie.
	 *
	 * ->gateway()
	 * ->setRefundable(true)
	 * ->setTokenBilling(true)
	 *
	 * @return Instance
     */
    public function boot(){}

	/**
	 * Returns the Omnipay driver
	 * @return object Omnipay initialized object
	 */
	protected function gateway()
    {

        $this->gateway = Omnipay::create($this->company_gateway->gateway->provider);
        $this->gateway->initialize((array) $this->company_gateway->getConfig());

        return $this;
    
	}


	public function setRefundable($value)
	{
		$this->refundable = $value;

		return $this;
	}

	/**
	 * Returns whether refunds are possible with the gateway
	 * @return boolean TRUE|FALSE
	 */
	public function getRefundable()
	{
		return $this->refundable;
	}

	public function setTokenBilling($value)
	{
		$this->token_billing = $value;

		return $this;
	}

	/**
	 * Returns whether token billing is possible with the gateway
	 * @return boolean TRUE|FALSE
	 */
	public function getTokenBilling() 
	{
		return $this->token_billing;
	}

	/**
	 * Refunds a given payment
	 * @return void 
	 */
	public function refundPayment() 
	{

	}
}