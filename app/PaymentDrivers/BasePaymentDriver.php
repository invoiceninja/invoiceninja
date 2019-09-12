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
use App\Models\GatewayType;
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

	/* The Invitation */
	protected $invitation;

	/* Member variables */
	protected $refundable = false;
	protected $token_billing = false;

    public function __construct(CompanyGateway $company_gateway, $invitation = false)
    {
        $this->company_gateway = $company_gateway;
        $this->invitation = $invitation;
        //$this->gatewayType = $gatewayType ?: $this->gatewayTypes()[0];
    }

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

	public function invoice()
	{
		return $this->invitation->invoice;
	}

	public function contact()
	{
		return $this->invitation->contact;
	}

	public function client()
	{
		return $this->contact()->client;
	}

	public function company()
	{
		return $this->invitation->company;
	}

	/**
	 * Returns the default gateway type
	 */
    public function gatewayTypes()
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

	/**
	 * Returns whether refunds are possible with the gateway
	 * @return boolean TRUE|FALSE
	 */
	public function getRefundable()
	{
		return $this->refundable;
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

	/************************************* Omnipay ******************************************
		authorize($options) - authorize an amount on the customer's card
		completeAuthorize($options) - handle return from off-site gateways after authorization
		capture($options) - capture an amount you have previously authorized
		purchase($options) - authorize and immediately capture an amount on the customer's card
		completePurchase($options) - handle return from off-site gateways after purchase
		refund($options) - refund an already processed transaction
		void($options) - generally can only be called up to 24 hours after submitting a transaction
		acceptNotification() - convert an incoming request from an off-site gateway to a generic notification object for further processing
	*/

	public function purchase($data, $items)
	{
		$response = $this->gateway
  						 ->purchase($data)
						 ->setItems($items)
						 ->send();


		if ($response->isRedirect()) {
		    // redirect to offsite payment gateway
		    $response->redirect();
		} elseif ($response->isSuccessful()) {
		    // payment was successful: update database
		    print_r($response);
		} else {
		    // payment failed: display message to customer
		    echo $response->getMessage();
		}
		/*
		$this->purchaseResponse = (array)$response->getData();*/
	}
	        
}