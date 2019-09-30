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

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Support\Facades\Auth;
use Omnipay\Omnipay;


/**
 * Class BasePaymentDriver
 * @package App\PaymentDrivers
 *
 *  Minimum dataset required for payment gateways
 *
 *  $data = [
        'amount' => $invoice->getRequestedAmount(),
        'currency' => $invoice->getCurrencyCode(),
        'returnUrl' => $completeUrl,
        'cancelUrl' => $this->invitation->getLink(),
        'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
        'transactionId' => $invoice->invoice_number,
        'transactionType' => 'Purchase',
        'clientIp' => Request::getClientIp(),
    ];

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

    protected $can_authorise_credit_card = false;


    public function __construct(CompanyGateway $company_gateway, Client $client, $invitation = false)
    {
        $this->company_gateway = $company_gateway;
        $this->invitation = $invitation;
        $this->client = $client;
        //$this->gatewayType = $gatewayType ?: $this->gatewayTypes()[0];
    }

	/**
	 * Returns the Omnipay driver
	 * @return object Omnipay initialized object
	 */
	protected function gateway()
    {
\Log::error("booting {$this->company_gateway->gateway->provider}");
        $this->gateway = Omnipay::create($this->company_gateway->gateway->provider);
        $this->gateway->initialize((array) $this->company_gateway->getConfig());

        return $this;
    
	}

	/**
	 * Return the configuration fields for the
	 * Gatway
	 * @return array The configuration fields
	 */
	public function getFields()
	{
		return $this->gateway->getDefaultParameters();
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

    public function getCompanyGatewayId()
    {
    	return $this->company_gateway->id;
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

	public function canAuthoriseCreditCard()
	{
		return $this->can_authorise_credit_card;
	}

	/**
	 * Refunds a given payment
	 * @return void 
	 */
	public function refundPayment() {}

	public function authorizeCreditCardView(array $data) {}

	public function authorizeCreditCardResponse($request) {}

	public function processPaymentView(array $data) {}
	
	public function processPaymentResponse($request) {}

	public function getContact()
	{
		if($this->invitation)
			return ClientContact::find($this->invitation->client_contact_id);
		elseif(auth()->guard('contact')->user())
			return auth()->user();
		else
			return false;
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

    protected function paymentDetails($input)
    {
       // $gatewayTypeAlias = $this->gatewayType == GatewayType::TOKEN ? $this->gatewayType : GatewayType::getAliasFromId($this->gatewayType);

        $data = [
            'currency' => $this->client->getCurrencyCode(),
            'transactionType' => 'Purchase',
            'clientIp' => request()->getClientIp(),
        ];
/*
        if ($paymentMethod) {
            if ($this->customerReferenceParam) {
                $data[$this->customerReferenceParam] = $paymentMethod->account_gateway_token->token;
            }
            $data[$this->sourceReferenceParam] = $paymentMethod->source_reference;
        } elseif ($this->input) {
            $data['card'] = new CreditCard($this->paymentDetailsFromInput($this->input));
        } else {
            $data['card'] = new CreditCard($this->paymentDetailsFromClient());
        }
*/
        return $data;
    }

	public function offsitePurchase($data, $items)
	{
		$this->gateway();

		$response =    	$this->gateway
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
    
	public function completePurchase($data)
	{
		$this->gateway();

		return $this->gateway
					->completePurchase($data)
					->send();
	}

}