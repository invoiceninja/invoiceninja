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

	public function init($api_key)
	{
        Stripe::setApiKey($api_key);
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

    /**
     * Creates a new String Payment Intent
     * @param  array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     */
    public function createIntent($data)
    {
        return PaymentIntent::create($data);
    }

    /**
     * Returns a setup intent that allows the user to enter card details without initiating a transaction.
     *
     * @return \Stripe\SetupIntent
     */
    public function getSetupIntent()
    {
        Stripe::setApiKey($this->company_gateway->getConfigField('23_apiKey'));

        return SetupIntent::create();
    }

    public function getPublishableKey()
    {
        return $this->company_gateway->getPublishableKey();
    }
	/************************************** Omnipay API methods **********************************************************/







}