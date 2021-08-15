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
        /* Step one - process a $1 payment - but don't complete it*/
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
            $result = $api_response->getBody();
            $payment = json_decode($result);
        } else {
            $errors = $api_response->getErrors();
            return $this->processUnsuccessfulPayment($errors);
        }

    
        /* Step 3 create the card */
        $card = new \Square\Models\Card();
        $card->setCardholderName($this->square_driver->client->present()->name());
        // $card->setBillingAddress($billing_address);
        $card->setCustomerId($this->findOrCreateClient());
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
            $card = json_decode($card);
        } else {
            $errors = $api_response->getErrors();

            return $this->processUnsuccessfulPayment($errors);
        }

        /* Create the token in Invoice Ninja*/
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
        // array (
        //   0 => 
        //   Square\Models\Error::__set_state(array(
        //      'category' => 'INVALID_REQUEST_ERROR',
        //      'code' => 'INVALID_CARD_DATA',
        //      'detail' => 'Invalid card data.',
        //      'field' => 'source_id',
        //   )),
        // )  

        $data = [
            'response' => $response,
            'error' => $response[0]['detail'],
            'error_code' => '',
        ];

        return $this->square_driver->processUnsuccessfulTransaction($data);

    }





    private function findOrCreateClient()
    {

        $email_address = new \Square\Models\CustomerTextFilter();
        $email_address->setExact($this->square_driver->client->present()->email());

        $filter = new \Square\Models\CustomerFilter();
        $filter->setEmailAddress($email_address);

        $query = new \Square\Models\CustomerQuery();
        $query->setFilter($filter);

        $body = new \Square\Models\SearchCustomersRequest();
        $body->setQuery($query);

        $api_response = $this->square_driver
                             ->init()
                             ->square
                             ->getCustomersApi()
                             ->searchCustomers($body);

        $customers = false;

        if ($api_response->isSuccess()) {
            $customers = $api_response->getBody();
            $customers = json_decode($customers);
        } else {
            $errors = $api_response->getErrors();
        }

        if($customers)
            return $customers->customers[0]->id;

        return $this->createClient();
    }

    private function createClient()
    {

        /* Step two - create the customer */
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
                             ->init()
                             ->square
                             ->getCustomersApi()
                             ->createCustomer($body);

        if ($api_response->isSuccess()) {
            $result = $api_response->getResult();
            return $result->getCustomer()->getId();

        } else {
            $errors = $api_response->getErrors();
            return $this->processUnsuccessfulPayment($errors);
        }

    }
}