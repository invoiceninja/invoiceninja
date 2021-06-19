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
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
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
        nlog("payment response");
        $this->wepay_payment_driver->init();

        //it could be an existing token or a new credit_card_id that needs to be converted into a wepay token
        if($request->has('credit_card_id') && $request->input('credit_card_id'))
        {
            nlog("authorize the card first!");

            $response = $this->wepay_payment_driver->wepay->request('credit_card/authorize', array(
                'client_id'          => config('ninja.wepay.client_id'),
                'client_secret'      => config('ninja.wepay.client_secret'),
                'credit_card_id'     => (int)$request->input('credit_card_id'),
            ));

            $credit_card_id = (int)$response->credit_card_id;

            if(in_array($response->state, ['new', 'authorized']) && boolval($request->input('store_card'))){

                $this->storePaymentMethod($response, GatewayType::CREDIT_CARD);

            }

        }
        else {

            $credit_card_id = (int)$request->input('token');

        }

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
                    'id'          => $credit_card_id
                )
            )
        ));

        /* Merge all data and store in the payment hash*/
        $state = [
            'server_response' => $response,
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $this->wepay_payment_driver->payment_hash->data = array_merge((array) $this->wepay_payment_driver->payment_hash->data, $state); 
        $this->wepay_payment_driver->payment_hash->save();


        if(in_array($response->state, ['authorized', 'captured'])){
            //success
            nlog("success");
            $payment_status = ($response->status == 'authorized') ? Payment::STATUS_COMPLETED : Payment:STATUS_PENDING;

            $this->processSuccessfulPayment($response, $payment_status);
        }

        if(in_array($response->state, ['released', 'cancelled', 'failed', 'expired'])){
            //some type of failure
            nlog("failure");

            $payment_status = ($response->status == 'cancelled') ? Payment::STATUS_CANCELLED : Payment:STATUS_FAILED;

            $this->processUnSuccessfulPayment($response, $payment_status);
        }

    }

/*
new The checkout was created by the application. This state typically indicates that checkouts created in WePay's hosted checkout flow are waiting for the payer to submit their information. 
authorized  The payer entered their payment info and confirmed the payment on WePay. WePay has successfully charged the card.
captured    The payment has been reserved from the payer.
released    The payment has been credited to the payee account. Note that the released state may be active although there are active partial refunds or partial chargebacks.
cancelled   The payment has been cancelled by the payer, payee, or application.
refunded    The payment was captured and then refunded by the payer, payee, or application. The payment has been debited from the payee account.
charged back    The payment has been charged back by the payer and the payment has been debited from the payee account.
failed  The payment has failed.
expired Checkouts expire if they remain in the new state for more than 30 minutes (e.g., they have been abandoned).
 */

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

    private function processSuccessfulPayment($response, $payment_status)
    {

        $data = [
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'amount' => $response->gross,
            'transaction_reference' => $response->checkout_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->wepay_payment_driver->createPayment($data, $payment_status);

         SystemLogger::dispatch(
            ['response' => $this->wepay_payment_driver->payment_hash->data->server_response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_WEPAY,
            $this->wepay_payment_driver->client,
            $this->wepay_payment_driver->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->wepay_payment_driver->encodePrimaryKey($payment->id)]);
    }

    private function processUnSuccessfulPayment($response, $payment_status)
    {
        PaymentFailureMailer::dispatch($this->wepay_payment_driver->client, $response->state, $this->wepay_payment_driver->client->company, $response->gross);

        PaymentFailureMailer::dispatch(
            $this->wepay_payment_driver->client,
            $response,
            $this->wepay_payment_driver->client->company,
            $response->gross
        );

        $message = [
            'server_response' => $response,
            'data' => $this->wepay_payment_driver->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_WEPAY,
            $this->wepay_payment_driver->client,
            $this->wepay_payment_driver->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

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

