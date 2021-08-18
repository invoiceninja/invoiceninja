<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\PaymentDrivers\Stripe\CreditCard;
use App\Utils\Ninja;

class SEPA
{
    /** @var StripePaymentDriver */
    public $stripe_driver;

    public function __construct(StripePaymentDriver $stripe_driver)
    {
        $this->stripe_driver = $stripe_driver;
    }

    public function authorizeView(array $data)
    {
        $customer = $this->stripe_driver->findOrCreateCustomer();

        $setup_intent = \Stripe\SetupIntent::create([
          'payment_method_types' => ['sepa_debit'],
          'customer' => $customer->id,
        ], $this->stripe_driver->stripe_connect_auth);

        $client_secret = $setup_intent->client_secret
        // Pass the client secret to the client


        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.sepa.authorize', array_merge($data));
    }


    public function paymentResponse(PaymentResponseRequest $request)
    {

        // $this->stripe_driver->init();

        // $state = [
        //     'server_response' => json_decode($request->gateway_response),
        //     'payment_hash' => $request->payment_hash,
        // ];

        // $state['payment_intent'] = \Stripe\PaymentIntent::retrieve($state['server_response']->id, $this->stripe_driver->stripe_connect_auth);

        // $state['customer'] = $state['payment_intent']->customer;

        // $this->stripe_driver->payment_hash->data = array_merge((array) $this->stripe_driver->payment_hash->data, $state);
        // $this->stripe_driver->payment_hash->save();

        // $server_response = $this->stripe_driver->payment_hash->data->server_response;

        // $response_handler = new CreditCard($this->stripe_driver);

        // if ($server_response->status == 'succeeded') {

        //     $this->stripe_driver->logSuccessfulGatewayResponse(['response' => json_decode($request->gateway_response), 'data' => $this->stripe_driver->payment_hash], SystemLog::TYPE_STRIPE);

        //     return $response_handler->processSuccessfulPayment();
        // }

        // return $response_handler->processUnsuccessfulPayment($server_response);


    }

    /* Searches for a stripe customer by email 
       otherwise searches by gateway tokens in StripePaymentdriver 
       finally creates a new customer if none found
    */
    private function getCustomer()
    {
        $searchResults = \Stripe\Customer::all([
                        "email" => $this->stripe_driver->client->present()->email(),
                        "limit" => 1,
                        "starting_after" => null
            ], $this->stripe_driver->stripe_connect_auth);
    

        if(count($searchResults) >= 1)
            return $searchResults[0];

        return $this->stripe_driver->findOrCreateCustomer();

    }   
}

