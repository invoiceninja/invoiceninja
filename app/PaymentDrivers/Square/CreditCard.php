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

namespace App\PaymentDrivers\Square;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\SquarePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreditCard
{
    use MakesHash;

    public $square_driver;

    public function __construct(SquarePaymentDriver $square_driver)
    {
        $this->square_driver = $square_driver;
        $this->square_driver->init();
    }

    public function authorizeView($data)
    {

        $data['gateway'] = $this->square_driver;

        return render('gateways.square.credit_card.authorize', $data);

    }

    public function authorizeResponse($request)
    {
        $payment = false;

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount(100); //amount in cents
        $amount_money->setCurrency($this->square_driver->client->currency()->code);

        $body = new \Square\Models\CreatePaymentRequest(
            $request->sourceId,
            Str::random(32),
            $amount_money
        );

        $body->setAutocomplete(false);
        $body->setLocationId($this->square_driver->company_gateway->getConfigField('locationId'));
        $body->setReferenceId(Str::random(16));

        $api_response = $this->square_driver->square->getPaymentsApi()->createPayment($body);

        if ($api_response->isSuccess()) {
            // $result = $api_response->getResult();

            $result = $api_response->getBody();
            $payment = json_decode($result);
            nlog($payment);

        } else {
            $errors = $api_response->getErrors();
            nlog($errors);
        }



/*
Success response looks like this:


{
  "payment": {
    "id": "Dv9xlBgSgVB8i6eT0imRYFjcrOaZY",
    "created_at": "2021-03-31T20:56:13.220Z",
    "updated_at": "2021-03-31T20:56:13.411Z",
    "amount_money": {
      "amount": 100,
      "currency": "USD"
    },
    "status": "COMPLETED",
    "delay_duration": "PT168H",
    "source_type": "CARD",
    "card_details": {
      "status": "CAPTURED",
      "card": {
        "card_brand": "AMERICAN_EXPRESS",
        "last_4": "6550",
        "exp_month": 3,
        "exp_year": 2023,
        "fingerprint": "sq-1-hPdOWUYtEMft3yQ",
        "card_type": "CREDIT",
        "prepaid_type": "NOT_PREPAID",
        "bin": "371263"
      },
      "entry_method": "KEYED",
      "cvv_status": "CVV_ACCEPTED",
      "avs_status": "AVS_ACCEPTED",
      "statement_description": "SQ *DEFAULT TEST ACCOUNT",
      "card_payment_timeline": {
        "authorized_at": "2021-03-31T20:56:13.334Z",
        "captured_at": "2021-03-31T20:56:13.411Z"
      }
    },
    "location_id": "VJN4XSBFTVPK9",
    "total_money": {
      "amount": 100,
      "currency": "USD"
    },
    "approved_money": {
      "amount": 100,
      "currency": "USD"
    }
   }
}
*/

        $billing_address = new \Square\Models\Address();
        $billing_address->setAddressLine1($this->square_driver->client->address1);
        $billing_address->setAddressLine2($this->square_driver->client->address2);
        $billing_address->setLocality($this->square_driver->client->city);
        $billing_address->setAdministrativeDistrictLevel1($this->square_driver->client->state);
        $billing_address->setPostalCode($this->square_driver->client->postal_code);
        $billing_address->setCountry($this->square_driver->client->country->iso_3166_2);

        $body = new \Square\Models\CreateCustomerRequest();
        $body->setGivenName($this->square_driver->client->present()->name());
        $body->setFamilyName('');
        $body->setEmailAddress($this->square_driver->client->present()->email());
        $body->setAddress($billing_address);
        $body->setPhoneNumber($this->square_driver->client->phone);
        $body->setReferenceId($this->square_driver->client->number);
        $body->setNote('Created by Invoice Ninja.');

        $api_response = $this->square_driver
                             ->square
                             ->getCustomersApi()
                             ->createCustomer($body);

        if ($api_response->isSuccess()) {
            $result = $api_response->getResult();
            nlog($result);
        } else {
            $errors = $api_response->getErrors();
            nlog($errors);
        }

/*Customer now created response

{
  "customer": {
    "id": "Q6VKKKGW8GWQNEYMDRMV01QMK8",
    "created_at": "2021-03-31T18:27:07.803Z",
    "updated_at": "2021-03-31T18:27:07Z",
    "given_name": "Amelia",
    "family_name": "Earhart",
    "email_address": "Amelia.Earhart@example.com",
    "preferences": {
      "email_unsubscribed": false
    }
  }
}

*/
        nlog("customer id = ".$result->getCustomer()->getId());
        nlog("source_id = ".$payment->payment->id);

        $card = new \Square\Models\Card();
        $card->setCardholderName($this->square_driver->client->present()->name());
        $card->setBillingAddress($billing_address);
        $card->setCustomerId($result->getCustomer()->getId());
        $card->setReferenceId(Str::random(8));

        $body = new \Square\Models\CreateCardRequest(
            Str::random(32),
            $payment->payment->id,
            $card
        );

        $api_response = $this->square_driver
                             ->square
                             ->getCardsApi()
                             ->createCard($body);

        $card = false;

        if ($api_response->isSuccess()) {
            $card = $api_response->getBody();
            nlog($card);
            $card = json_decode($card);

            nlog("ressy");
            nlog($result);
        } else {
            $errors = $api_response->getErrors();
            nlog("i got errors");
            nlog($errors);
        }

/**
 * 
{
  "card": {
    "id": "ccof:uIbfJXhXETSP197M3GB", //this is the token
    "billing_address": {
      "address_line_1": "500 Electric Ave",
      "address_line_2": "Suite 600",
      "locality": "New York",
      "administrative_district_level_1": "NY",
      "postal_code": "10003",
      "country": "US"
    },
    "bin": "411111",
    "card_brand": "VISA",
    "card_type": "CREDIT",
    "cardholder_name": "Amelia Earhart",
    "customer_id": "Q6VKKKGW8GWQNEYMDRMV01QMK8",
    "enabled": true,
    "exp_month": 11,
    "exp_year": 2018,
    "last_4": "1111",
    "prepaid_type": "NOT_PREPAID",
    "reference_id": "user-id-1",
    "version": 1
  }
}

*/

        $cgt = [];
        $cgt['token'] = $card->card->id;
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $card->card->exp_month;
        $payment_meta->exp_year = $card->card->exp_year;
        $payment_meta->brand = $card->card->card_brand;
        $payment_meta->last4 = $card->card->last_4;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $token = $this->square_driver->storeGatewayToken($cgt, []);

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView($data)
    {

        $data['gateway'] = $this->square_driver;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return render('gateways.braintree.credit_card.pay', $data);

    }

    public function processPaymentResponse($request)
    {
        
    }

    /* This method is stubbed ready to go - you just need to harvest the equivalent 'transaction_reference' */
    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->square_driver->payment_hash->invoices(), 'amount')) + $this->square_driver->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        // $payment_record['transaction_reference'] = $response->transaction_id;

        $payment = $this->square_driver->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }

    private function processUnsuccessfulPayment($response)
    {
        /*Harvest your own errors here*/
        // $error = $response->status_message;

        // if(property_exists($response, 'approval_message') && $response->approval_message)
        //     $error .= " - {$response->approval_message}";

        // $error_code = property_exists($response, 'approval_message') ? $response->approval_message : 'Undefined code';

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $error_code,
        ];

        return $this->square_driver->processUnsuccessfulTransaction($data);

    }


    /* Helpers */

    /*
      You will need some helpers to handle successful and unsuccessful responses

      Some considerations after a succesful transaction include:

      Logging of events: success +/- failure
      Recording a payment 
      Notifications
     */




}