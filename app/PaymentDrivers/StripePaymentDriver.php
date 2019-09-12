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
use Stripe\Stripe;

class StripePaymentDriver extends BasePaymentDriver
{
	protected $refundable = true;

	protected $token_billing = true;

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
    			return 'gateways.stripe.credit_card';
    			break;
    		case GatewayType::TOKEN:
    			return 'gateways.stripe.token';
    			break;
    		case GatewayType::SOFORT:
    			return 'gateways.stripe.sofort';
    			break;
    		case GatewayType::BANK_TRANSFER:
    			return 'gateways.stripe.ach';
    			break;
    		case GatewayType::SEPA:
    			return 'gateways.stripe.sepa';
    			break;
    		case GatewayType::BITCOIN:
    			return 'gateways.stripe.other';
    			break;
    		case GatewayType::ALIPAY:
    			return 'gateways.stripe.other';
    			break;
    		case GatewayType::APPLE_PAY:
    			return 'gateways.stripe.other';
    			break;

    		default:
    			# code...
    			break;
    	}
    }
	/************************************** Omnipay API methods **********************************************************/







}