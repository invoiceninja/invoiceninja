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

    public $square_class;

    public function __construct(SquarePaymentDriver $square_class)
    {
        $this->square_class = $square_class;
    }

    public function authorizeView($data)
    {

        $data['gateway'] = $this->square_class;

        return render('gateways.square.credit_card.authorize', $data);

    }

    public function authorizeRequest($request)
    {

        $billing_address = new \Square\Models\Address();
        $billing_address->setAddressLine1($this->square_class->client->address1);
        $billing_address->setAddressLine2($this->square_class->client->address2);
        $billing_address->setLocality($this->square_class->client->city);
        $billing_address->setAdministrativeDistrictLevel1($this->square_class->client->state);
        $billing_address->setPostalCode($this->square_class->client->postal_code);
        $billing_address->setCountry($this->square_class->client->country->iso_3166_2);

        $card = new \Square\Models\Card();
        $card->setCardholderName('Amelia Earhart');
        $card->setBillingAddress($billing_address);
        $card->setCustomerId('VDKXEEKPJN48QDG3BGGFAK05P8');
        $card->setReferenceId('user-id-1');

        $body = new \Square\Models\CreateCardRequest(
            '4935a656-a929-4792-b97c-8848be85c27c',
            'cnon:uIbfJXhXETSP197M3GB',
            $card
        );

        $api_response = $client->getCardsApi()->createCard($body);

        if ($api_response->isSuccess()) {
            $result = $api_response->getResult();
        } else {
            $errors = $api_response->getErrors();
        }


        return back();
    }

    public function paymentView($data)
    {

        $data['gateway'] = $this->square_class;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return render('gateways.braintree.credit_card.pay', $data);

    }

    public function processPaymentResponse($request)
    {
        
    }

    /* This method is stubbed ready to go - you just need to harvest the equivalent 'transaction_reference' */
    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->square_class->payment_hash->invoices(), 'amount')) + $this->square_class->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        // $payment_record['transaction_reference'] = $response->transaction_id;

        $payment = $this->square_class->createPayment($payment_record, Payment::STATUS_COMPLETED);

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

        return $this->square_class->processUnsuccessfulTransaction($data);

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