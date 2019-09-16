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

use App\Models\GatewayType;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\Stripe;

class StripePaymentDriver extends BasePaymentDriver
{
	protected $refundable = true;

	protected $token_billing = true;

    protected $can_authorise_credit_card = true;

	protected $customer_reference = 'customerReferenceParam';


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
	/************************************** Stripe API methods **********************************************************/

	public function init()
	{
        Stripe::setApiKey($this->company_gateway->getConfigField('23_apiKey'));
	}
	/**
	 * Returns the gateway types
	 */
    public function gatewayTypes() :array
    {
        $types = [
            GatewayType::CREDIT_CARD,
            GatewayType::TOKEN,
        ];
        
        if($this->company_gateway->getSofortEnabled() && $this->invitation && $this->client() && isset($this->client()->country) && in_array($this->client()->country, ['AUT', 'BEL', 'DEU', 'ITA', 'NLD', 'ESP']))
            $types[] = GatewayType::SOFORT;

	    if($this->company_gateway->getAchEnabled())
	    	$types[] = GatewayType::BANK_TRANSFER;

        if ($this->company_gateway->getSepaEnabled()) 
            $types[] = GatewayType::SEPA;
        
        if ($this->company_gateway->getBitcoinEnabled()) 
            $types[] = GatewayType::BITCOIN;
        
        if ($this->company_gateway->getAlipayEnabled()) 
            $types[] = GatewayType::ALIPAY;
        
        if ($this->company_gateway->getApplePayEnabled()) 
            $types[] = GatewayType::APPLE_PAY;
            

        return $types;

    }

    public function viewForType($payment_type)
    {
    	switch ($payment_type) {
    		case GatewayType::CREDIT_CARD:
    			return 'portal.default.gateways.stripe.credit_card';
    			break;
    		case GatewayType::TOKEN:
    			return 'portal.default.gateways.stripe.credit_card';
    			break;
    		case GatewayType::SOFORT:
    			return 'portal.default.gateways.stripe.sofort';
    			break;
    		case GatewayType::BANK_TRANSFER:
    			return 'portal.default.gateways.stripe.ach';
    			break;
    		case GatewayType::SEPA:
    			return 'portal.default.gateways.stripe.sepa';
    			break;
    		case GatewayType::BITCOIN:
    			return 'portal.default.gateways.stripe.other';
    			break;
    		case GatewayType::ALIPAY:
    			return 'portal.default.gateways.stripe.other';
    			break;
    		case GatewayType::APPLE_PAY:
    			return 'portal.default.gateways.stripe.other';
    			break;

    		default:
    			# code...
    			break;
    	}
    }

    public function authorizeCreditCardView($data)
    {
        $intent['intent'] = $this->getSetupIntent();

        return view('portal.default.gateways.stripe.create_customer', array_merge($data, $intent));

    }

    public function authorizeCreditCardResponse($request)
    {
        /**
         * {
              "id": "seti_1FJHmuKmol8YQE9DdhDgFXhT",
              "object": "setup_intent",
              "cancellation_reason": null,
              "client_secret": "seti_1FJHmuKmol8YQE9DdhDgFXhT_secret_FoveetSB7RewVngU7H6IcrH9dlM1BXd",
              "created": 1568631032,
              "description": null,
              "last_setup_error": null,
              "livemode": false,
              "next_action": null,
              "payment_method": "pm_1FJHvQKmol8YQE9DV19fPXXk",
              "payment_method_types": [
                "card"
              ],
              "status": "succeeded",
              "usage": "off_session"
 
            }


\Stripe\Stripe::setApiKey('sk_test_faU9gVB7Hx19fCTo0e5ggZ0x');

\Stripe\PaymentMethod::retrieve('pm_1EUmzw2xToAoV8choYUtciXR');


{
  "id": "pm_1EUmzw2xToAoV8choYUtciXR",
  "object": "payment_method",
  "card": {
    "brand": "visa",
    "checks": {
      "address_line1_check": null,
      "address_postal_code_check": null,
      "cvc_check": null
    },
    "country": "US",
    "exp_month": 8,
    "exp_year": 2020,
    "fingerprint": "sStRRZt3Xlw0Ec6B",
    "funding": "credit",
    "generated_from": null,
    "last4": "4242",
    "three_d_secure_usage": {
      "supported": true
    },
    "wallet": null
  },
  "created": 1556596276,
  "customer": "cus_3fAHf0I56s1QFx",
  "livemode": false,
  "metadata": {},
  "type": "card"
}

         */


        //get the customer or create a new one.
        //get the payment method
        //attached payment method to customer
        //store meta data



    }

    /**
     * Creates a new String Payment Intent
     * @param  array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     */
    public function createIntent($data)
    {
        $this->init();
        return PaymentIntent::create($data);
    }

    /**
     * Returns a setup intent that allows the user to enter card details without initiating a transaction.
     *
     * @return \Stripe\SetupIntent
     */
    public function getSetupIntent()
    {
        $this->init();
        return SetupIntent::create();
    }

    public function getPublishableKey()
    {
        return $this->company_gateway->getPublishableKey();
    }

    public function findOrCreateCustomer() :?\Stripe\Customer
    { 

        $customer = null;

        $this->init();

        $client_gateway_token = $this->client->gateway_tokens->whereGatewayId($this->company_gateway->gateway_id)->first();

        if($client_gateway_token->gateway_customer_reference)
            $customer = \Stripe\Customer::retrieve($client_gateway_token->gateway_customer_reference);
        else{
            $customer = \Stripe\Customer::create([
              "email" => $this->client->present()->email(),
              "name" => $this->client->present()->name(),
              "phone" => $this->client->present()->phone(),
            ]);
        }
        return $customer;
    }


	/************************************** Omnipay API methods **********************************************************/







}