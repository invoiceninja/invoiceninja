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

use App\Factory\PaymentFactory;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\Stripe;

class StripePaymentDriver extends BasePaymentDriver
{
  use MakesHash;

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
            //GatewayType::TOKEN,
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

    public function viewForType($gateway_type_id)
    {
    	switch ($gateway_type_id) {
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

    /**
     * 
     * Authorises a credit card for future use
     * @param  array  $data Array of variables needed for the view
     * 
     * @return view       The gateway specific partial to be rendered
     * 
     */
    public function authorizeCreditCardView(array $data)
    {

        $intent['intent'] = $this->getSetupIntent();

        return view('portal.default.gateways.stripe.add_credit_card', array_merge($data, $intent));

    }

    /**
     * Processes the gateway response for credti card authorization
     * @param  Request $request The returning request object
     * @return view          Returns the user to payment methods screen.
     */
    public function authorizeCreditCardResponse($request)
    {

        $server_response = json_decode($request->input('gateway_response'));

        $gateway_id = $request->input('gateway_id');
        $gateway_type_id = $request->input('gateway_type_id');
        $is_default = $request->input('is_default');

        $payment_method = $server_response->payment_method;

        $customer = $this->findOrCreateCustomer();

        $this->init();
        $stripe_payment_method = \Stripe\PaymentMethod::retrieve($payment_method);
        $stripe_payment_method_obj = $stripe_payment_method->jsonSerialize();
        $stripe_payment_method->attach(['customer' => $customer->id]);

        $payment_meta = new \stdClass;

        if($stripe_payment_method_obj['type'] == 'card') {
            $payment_meta->exp_month = $stripe_payment_method_obj['card']['exp_month'];
            $payment_meta->exp_year = $stripe_payment_method_obj['card']['exp_year'];
            $payment_meta->brand = $stripe_payment_method_obj['card']['brand'];
            $payment_meta->last4 = $stripe_payment_method_obj['card']['last4'];
            $payment_meta->type = $stripe_payment_method_obj['type'];
        }

        $cgt = new ClientGatewayToken;
        $cgt->company_id = $this->client->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->token = $payment_method;
        $cgt->company_gateway_id = $this->company_gateway->id;
        $cgt->gateway_type_id = $gateway_type_id;
        $cgt->gateway_customer_reference = $customer->id;
        $cgt->meta = $payment_meta;
        $cgt->save();

        if($is_default == 'true' || $this->client->gateway_tokens->count() == 1)
        {
            $this->client->gateway_tokens()->update(['is_default'=>0]);

            $cgt->is_default = 1;
            $cgt->save();
        }

        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Processes the payment with this gateway
     *             
     * @var         invoices
     * @var         amount
     * @var         fee
     * @var         amount_with_fee
     * @var         token
     * @var         payment_method_id
     * @var         hashed_ids
     * 
     * @param  array  $data variables required to build payment page
     * @return view   Gateway and payment method specific view
     */
    public function processPaymentView(array $data)
    {
        $payment_intent_data = [
            'amount' => $this->convertToStripeAmount($data['amount_with_fee'], $this->client->currency->precision),
            'currency' => $this->client->getCurrencyCode(),
            'customer' => $this->findOrCreateCustomer(),
            'description' => $data['invoices']->pluck('id'), //todo more meaningful description here:
        ];

        if($data['token'])
            $payment_intent_data['payment_method'] = $data['token']->token;
        else{
            $payment_intent_data['setup_future_usage']  = 'off_session';
//            $payment_intent_data['save_payment_method'] = true;
//            $payment_intent_data['confirm'] = true;
        }


        $data['intent'] = $this->createPaymentIntent($payment_intent_data);
        $data['gateway'] = $this;

        return view($this->viewForType($data['payment_method_id']), $data);

    }

    /**
     * Payment Intent Reponse looks like this
      +"id": "pi_1FMR7JKmol8YQE9DuC4zMeN3"
      +"object": "payment_intent"
      +"allowed_source_types": array:1 [▼
        0 => "card"
      ]
      +"amount": 2372484
      +"canceled_at": null
      +"cancellation_reason": null
      +"capture_method": "automatic"
      +"client_secret": "pi_1FMR7JKmol8YQE9DuC4zMeN3_secret_J3yseWJG6uV0MmsrAT1FlUklV"
      +"confirmation_method": "automatic"
      +"created": 1569381877
      +"currency": "usd"
      +"description": "[3]"
      +"last_payment_error": null
      +"livemode": false
      +"next_action": null
      +"next_source_action": null
      +"payment_method": "pm_1FMR7ZKmol8YQE9DQWqPuyke"
      +"payment_method_types": array:1 [▶]
      +"receipt_email": null
      +"setup_future_usage": "off_session"
      +"shipping": null
      +"source": null
      +"status": "succeeded"
    */
    public function processPaymentResponse($request)
    {
        $server_response = json_decode($request->input('gateway_response'));

        $payment_method = $server_response->payment_method;
        $payment_status = $server_response->status;
        $save_card = $request->input('store_card');

        $gateway_type_id = $request->input('payment_method_id');
        $hashed_ids = $request->input('hashed_ids');
        $invoices = Invoice::whereIn('id', $this->transformKeys(explode(",",$hashed_ids)))
                                ->whereClientId($this->client->id)
                                ->get();
        /**
         * Potential statuses that can be returned
         * 
         * requires_action
         * processing
         * canceled
         * requires_action
         * requires_confirmation
         * requires_payment_method 
         * 
         */
        
        if($this->getContact()){
          $client_contact = $this->getContact();
        }
        else{
          $client_contact = $invoices->first()->invitations->first()->contact;
        }

        $this->init();
        $payment_intent = \Stripe\PaymentIntent::retrieve($server_response->id);
        $customer = $payment_intent->customer;

        if($payment_status == 'succeeded')
        {
            $this->init();
            $stripe_payment_method = \Stripe\PaymentMethod::retrieve($payment_method);
            $stripe_payment_method_obj = $stripe_payment_method->jsonSerialize();

            $payment_meta = new \stdClass;

            if($stripe_payment_method_obj['type'] == 'card') {
                $payment_meta->exp_month = $stripe_payment_method_obj['card']['exp_month'];
                $payment_meta->exp_year = $stripe_payment_method_obj['card']['exp_year'];
                $payment_meta->brand = $stripe_payment_method_obj['card']['brand'];
                $payment_meta->last4 = $stripe_payment_method_obj['card']['last4'];
                $payment_meta->type = $stripe_payment_method_obj['type'];

                $payment_type = PaymentType::parseCardType($stripe_payment_method_obj['card']['brand']);
            }

            if($save_card == 'true')
            {

                $stripe_payment_method->attach(['customer' => $customer]);
                
                $cgt = new ClientGatewayToken;
                $cgt->company_id = $this->client->company->id;
                $cgt->client_id = $this->client->id;
                $cgt->token = $payment_method;
                $cgt->company_gateway_id = $this->company_gateway->id;
                $cgt->gateway_type_id = $gateway_type_id;
                $cgt->gateway_customer_reference = $customer;
                $cgt->meta = $payment_meta;
                $cgt->save();

                if($this->client->gateway_tokens->count() == 1)
                {
                    $this->client->gateway_tokens()->update(['is_default'=>0]);

                    $cgt->is_default = 1;
                    $cgt->save();
                }  
            }
        
            //todo need to fix this to support payment types other than credit card.... sepa etc etc
            if(!$payment_type)
              $payment_type = PaymentType::CREDIT_CARD_OTHER;

            $payment = PaymentFactory::create($this->client->company->id, $this->client->user->id);
            $payment->client_id = $this->client->id;
            $payment->client_contact_id = $client_contact->id;
            $payment->company_gateway_id = $this->company_gateway->id;
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->amount = $this->convertFromStripeAmount($server_response->amount, $this->client->currency->precision);
            $payment->payment_date = Carbon::now();
            $payment->payment_type_id = $payment_type;
            $payment->transaction_reference = $payment_method;
            $payment->save();

            $payment->invoices()->sync($invoices);
            $payment->save();


//mark all invoices as paid
//$invoices->update(['status_id' => Payment::STATUS_COMPLETED]);

  // foreach($invoices as $invoice){
  //   \Log::error('invite count = '.$invoices->invitations->count());
  //   foreach($invoice->invitations as $invitation)
  //   {
  //     \Log::error($invitation);
  //     $invitations->update(['transaction_reference' => $payment->transaction_reference]);
  //   }
  // }

//mark all invitations with transaction reference
$invoices->each(function ($invoice) use($payment) {

  $invoice->status_id = Payment::STATUS_COMPLETED;
  $invoice->save();
  $invoice->invitations()->update(['transaction_reference' => $payment->transaction_reference]);

});
            return redirect()->route('client.payments.show', ['id' => $this->encodePrimaryKey($payment->id)]);

        }

    }

    private function convertFromStripeAmount($amount, $precision)
    {
      return $amount / pow(10, $precision);
    }

    private function convertToStripeAmount($amount, $precision)
    {
      return $amount * pow(10, $precision);
    }
    /**
     * Creates a new String Payment Intent
     * 
     * @param  array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     */
    public function createPaymentIntent($data) :?\Stripe\PaymentIntent
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

        if(!$customer)
            throw new Exception('Unable to create gateway customer');

        return $customer;
    }


	/************************************** Omnipay API methods **********************************************************/







}