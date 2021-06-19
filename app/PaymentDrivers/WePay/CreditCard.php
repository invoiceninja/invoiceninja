<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\WePay;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\GatewayType;
use App\PaymentDrivers\WePayPaymentDriver;

class CreditCard
{
    public $wepay_payment_driver;

    public function __construct(WePayPaymentDriver $wepay_payment_driver)
    {
        $this->wepay_payment_driver = $wepay_payment_driver;
    }

    public function authorizeView($data)
    {
        $data['gateway'] = $this->wepay_payment_driver;
        
        return render('gateways.wepay.authorize.authorize', $data);
    }

 	public function authorizeResponse($request)
 	{
 		//https://developer.wepay.com/api/api-calls/credit_card#authorize
        $data = $request->all();
		// authorize the credit card
        
        nlog($data);
        /*
          '_token' => '1Fk5CRj34up5ntKPvrFyMIAJhDdUNF3boqT3iIN3',
          'company_gateway_id' => '39',
          'payment_method_id' => '1',
          'gateway_response' => NULL,
          'is_default' => NULL,
          'credit_card_id' => '180642154638',
          'q' => '/client/payment_methods',
          'method' => '1',
         */

		$response = $this->wepay_payment_driver->wepay->request('credit_card/authorize', array(
		    'client_id'          => config('ninja.wepay.client_id'),
		    'client_secret'      => config('ninja.wepay.client_secret'),
		    'credit_card_id'     => (int)$data['credit_card_id'],
		));

		// display the response
        // nlog($response);
        
        if(in_array($response->state, ['new', 'authorized'])){

            $this->storePaymentMethod($response, GatewayType::CREDIT_CARD);

            return redirect()->route('client.payment_methods.index');
        }
    
        throw new PaymentFailed("There was a problem adding this payment method.", 400);
        
        /*
            [credit_card_id] => 348084962473
            [credit_card_name] => Visa xxxxxx4018
            [state] => authorized
            [user_name] => Joey Diaz
            [email] => user@example.com
            [create_time] => 1623798172
            [expiration_month] => 10
            [expiration_year] => 2023
            [last_four] => 4018
            [input_source] => card_keyed
            [virtual_terminal_mode] => none
            [card_on_file] => 
            [recurring] => 
            [cvv_provided] => 1
            [auto_update] => 
        */    
    
 	}  


    public function paymentView(array $data)
    {
        $data['gateway'] = $this->wepay_payment_driver;

        return render('gateways.wepay.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        // USD, CAD, and GBP.
        nlog($request->all());
        // charge the credit card
        $response = $this->wepay_payment_driver->wepay->request('checkout/create', array(
            'account_id'          => $this->wepay_payment_driver->company_gateway->getConfigField('accountId'),
            'amount'              => $this->wepay_payment_driver->payment_hash->data->amount_with_fee,
            'currency'            => $this->wepay_payment_driver->client->getCurrencyCode(),
            'short_description'   => 'A vacation home rental',
            'type'                => 'goods',
            'payment_method'      => array(
                'type'            => 'credit_card',
                'credit_card'     => array(
                    'id'          => $data['token']
                )
            )
        ));

    }

/*
https://developer.wepay.com/api/api-calls/checkout
{
    "checkout_id": 649945633,
    "account_id": 1548718026,
    "type": "donation",
    "short_description": "test checkout",
    "currency": "USD",
    "amount": 20,
    "state": "authorized",
    "soft_descriptor": "WPY*Wolverine",
    "auto_release": true,
    "create_time": 1463589958,
    "gross": 20.88,
    "reference_id": null,
    "callback_uri": null,
    "long_description": null,
    "delivery_type": null,
    "initiated_by": "merchant",
    "in_review": false,
    "fee": {
        "app_fee": 0,
        "processing_fee": 0.88,
        "fee_payer": "payer"
    },
    "chargeback": {
        "amount_charged_back": 0,
        "dispute_uri": null
    },
    "refund": {
        "amount_refunded": 0,
        "refund_reason": null
    },
    "payment_method": {
        "type": "credit_card",
        "credit_card": {
            "id": 1684847614,
            "data": {
                "emv_receipt": null,
                "signature_url": null
            },
            "auto_release": false
        }
    },
    "hosted_checkout": null,
    "payer": {
        "email": "test@example.com",
        "name": "Mr Smith",
        "home_address": null
    },
    "npo_information": null,
    "payment_error": null
}
 */

    private function storePaymentMethod($response, $payment_method_id)
    {

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = (string) $response->expiration_month;
        $payment_meta->exp_year = (string) $response->expiration_year;
        $payment_meta->brand = (string) $response->credit_card_name;
        $payment_meta->last4 = (string) $response->last_four;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $response->credit_card_id,
            'payment_method_id' => $payment_method_id,
        ];

        $this->wepay_payment_driver->storeGatewayToken($data);

    } 



}

