<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Square;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\SquarePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Square\Http\ApiResponse;

class CreditCard implements MethodInterface
{
    use MakesHash;

    public $square_driver;

    public function __construct(SquarePaymentDriver $square_driver)
    {
        $this->square_driver = $square_driver;
        $this->square_driver->init();
    }

    /**
     * Authorization page for credit card.
     *
     * @param array $data
     * @return View
     */
    public function authorizeView($data): View
    {
        $data['gateway'] = $this->square_driver;

        return render('gateways.square.credit_card.authorize', $data);
    }

    /**
     * Handle authorization for credit card.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function authorizeResponse($request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView($data)
    {
        $data['gateway'] = $this->square_driver;
        $data['amount'] = $this->square_driver->payment_hash->data->amount_with_fee;
        $data['currencyCode'] = $this->square_driver->client->getCurrencyCode();
        $data['square_contact'] = $this->buildClientObject();

        return render('gateways.square.credit_card.pay', $data);
    }

    private function buildClientObject()
    {
        $client = new \stdClass;

        $client->addressLines = [$this->square_driver->client->address1 ?: '', $this->square_driver->client->address2 ?: ''];
        $client->givenName = $this->square_driver->client->present()->first_name();
        $client->familyName = $this->square_driver->client->present()->last_name();
        $client->email = $this->square_driver->client->present()->email;
        $client->phone = $this->square_driver->client->phone;
        $client->city = $this->square_driver->client->city;
        $client->region = $this->square_driver->client->state;
        $client->country = $this->square_driver->client->country->iso_3166_2;

        return (array) $client;
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $token = $request->sourceId;

        $amount = $this->square_driver->convertAmount(
            $this->square_driver->payment_hash->data->amount_with_fee
        );

        if ($request->shouldUseToken()) {
            $cgt = ClientGatewayToken::where('token', $request->token)->first();
            $token = $cgt->token;
        }

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($amount);
        $amount_money->setCurrency($this->square_driver->client->currency()->code);

        $body = new \Square\Models\CreatePaymentRequest($token, $request->idempotencyKey, $amount_money);

        $body->setAutocomplete(true);
        $body->setLocationId($this->square_driver->company_gateway->getConfigField('locationId'));
        $body->setReferenceId(Str::random(16));

        if ($request->has('verificationToken') && $request->input('verificationToken')) {
            $body->setVerificationToken($request->input('verificationToken'));
        }

        if ($request->shouldUseToken()) {
            $body->setCustomerId($cgt->gateway_customer_reference);
        }

        /** @var ApiResponse */
        $response = $this->square_driver->square->getPaymentsApi()->createPayment($body);

        if ($response->isSuccess()) {
            return $this->processSuccessfulPayment($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    private function processSuccessfulPayment(ApiResponse $response)
    {
        $body = json_decode($response->getBody());

        $amount = array_sum(array_column($this->square_driver->payment_hash->invoices(), 'amount')) + $this->square_driver->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $body->payment->id;

        $payment = $this->square_driver->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment(ApiResponse $response)
    {
        $body = \json_decode($response->getBody());

        $data = [
            'response' => $response,
            'error' => $body->errors[0]->detail,
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

            if (count([$api_response->getBody(), 1]) == 0) {
                $customers = false;
            }
        } else {
            $errors = $api_response->getErrors();
        }

        if (property_exists($customers, 'customers')) {
            return $customers->customers[0]->id;
        }

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
