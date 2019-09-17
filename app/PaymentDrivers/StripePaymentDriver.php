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

use App\Models\ClientGatewayToken;
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
 * Payments
 * \Stripe\PaymentIntent::create([
    'payment_method_types' => ['card'],
    'amount' => 1099,
    'currency' => 'aud',
    'customer' => 'cus_Fow2nmVJX1EsQw',
    'payment_method' => 'card_1FJIAjKmol8YQE9DxWb9kMpR',
]);
 */


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

    /**
     * Initializes the Stripe API
     * @return void
     */
	public function init() :void
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
        \Log::error($request->all());

        $server_response = json_decode($request->input('gateway_response'));


        $gateway_id = $request->input('gateway_id');
        $gateway_type_id = $request->input('payment_method_id');
        $is_default = $request->input('is_default');

        $payment_method = $server_response->payment_method;

        $this->init();

        $customer = $this->findOrCreateCustomer();

        $stripe_payment_method = \Stripe\PaymentMethod::retrieve($payment_method);
        $stripe_payment_method->attach(['customer' => $customer->id]);

        $cgt = new ClientGatewayToken;
        $cgt->company_id = $this->client->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->token = $payment_method;
        $cgt->company_gateway_id = $this->company_gateway->id;
        $cgt->payment_method_id = $gateway_type_id;
        $cgt->gateway_customer_reference = $customer->id;
        $cgt->save();


        if($is_default == 'true')
        {
            $this->client->gateway_tokens()->update(['is_default'=>0]);

            $cgt->is_default = 1;
            $cgt->save();
        }

        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Creates a new String Payment Intent
     * 
     * @param  array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     */
    public function createIntent($data) :?\Stripe\PaymentIntent
    {

        $this->init();

        return PaymentIntent::create($data);

    }

    /**
     * Returns a setup intent that allows the user 
     * to enter card details without initiating a transaction.
     *
     * @return \Stripe\SetupIntent
     */
    public function getSetupIntent() :\Stripe\SetupIntent
    {

        $this->init();

        return SetupIntent::create();

    }


    /**
     * Returns the Stripe publishable key
     * @return NULL|string The stripe publishable key
     */
    public function getPublishableKey() :?string
    {

        return $this->company_gateway->getPublishableKey();

    }

    /**
     * Finds or creates a Stripe Customer object
     * 
     * @return NULL|\Stripe\Customer A Stripe customer object
     */
    public function findOrCreateCustomer() :?\Stripe\Customer
    { 

        $customer = null;

        $this->init();

        $client_gateway_token = ClientGatewayToken::whereClientId($this->client->id)->whereCompanyGatewayId($this->company_gateway->id)->first();

        if($client_gateway_token && $client_gateway_token->gateway_customer_reference){

            $customer = \Stripe\Customer::retrieve($client_gateway_token->gateway_customer_reference);

        }
        else{

            $data['name'] = $this->client->present()->name();
            $data['phone'] = $this->client->present()->phone();

            if(filter_var($this->client->present()->email(), FILTER_VALIDATE_EMAIL))
                $data['email'] = $this->client->present()->email();

            $customer = \Stripe\Customer::create($data);

        }

        return $customer;
    }


	/************************************** Omnipay API methods **********************************************************/







}