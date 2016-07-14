<?php

namespace App\Ninja\PaymentDrivers;

use App\Models\PaymentMethod;
use Exception;
use Session;
use Braintree\Customer;

/**
 * Class BraintreePaymentDriver
 */
class BraintreePaymentDriver extends BasePaymentDriver
{
    /**
     * @var string
     */
    protected $customerReferenceParam = 'customerId';

    /**
     * @var string
     */
    protected $sourceReferenceParam = 'paymentMethodToken';

    /**
     * @return array
     */
    public function gatewayTypes()
    {
        $types = [
            GATEWAY_TYPE_CREDIT_CARD,
            GATEWAY_TYPE_TOKEN,
        ];

        if ($this->accountGateway && $this->accountGateway->getPayPalEnabled()) {
            $types[] = GATEWAY_TYPE_PAYPAL;
        }

        return $types;
    }

    /**
     * @return bool
     */
    public function tokenize()
    {
        return true;
    }

    /**
     * @param array $input
     * @param bool $sourceId
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function startPurchase(array $input = false, $sourceId = false)
    {
        $data = parent::startPurchase($input, $sourceId);

        if ($this->isGatewayType(GATEWAY_TYPE_PAYPAL)) {
            $data['details'] = ! empty($input['device_data']) ? json_decode($input['device_data']) : false;
        }

        return $data;
    }

    /**
     * @param $customer
     *
     * @return bool
     */
    protected function checkCustomerExists($customer)
    {
        if ( ! parent::checkCustomerExists($customer)) {
            return false;
        }

        $customer = $this->gateway()->findCustomer($customer->token)
            ->send()
            ->getData();

        return ($customer instanceof Customer);
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return array
     */
    protected function paymentDetails(PaymentMethod $paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        $deviceData = array_get($this->input, 'device_data') ?: Session::get($this->invitation->id . 'device_data');

        if ($deviceData) {
            $data['device_data'] = $deviceData;
        }

        if ($this->isGatewayType(GATEWAY_TYPE_PAYPAL, $paymentMethod)) {
            $data['ButtonSource'] = 'InvoiceNinja_SP';
        }

        if ( ! $paymentMethod && ! empty($this->input['sourceToken'])) {
            $data['token'] = $this->input['sourceToken'];
        }

        return $data;
    }

    /**
     * @return PaymentMethod|bool
     */
    public function createToken()
    {
        if ($customer = $this->customer()) {
            $customerReference = $customer->token;
        } else {
            $data = $this->paymentDetails();
            $tokenResponse = $this->gateway()->createCustomer(['customerData' => $this->customerData()])->send();
            if ($tokenResponse->isSuccessful()) {
                $customerReference = $tokenResponse->getCustomerData()->id;
            } else {
                return false;
            }
        }

        if ($customerReference) {
            $data['customerId'] = $customerReference;

            if ($this->isGatewayType(GATEWAY_TYPE_PAYPAL)) {
                $data['paymentMethodNonce'] = $this->input['sourceToken'];
            }

            $tokenResponse = $this->gateway->createPaymentMethod($data)->send();
            if ($tokenResponse->isSuccessful()) {
                $this->tokenResponse = $tokenResponse->getData()->paymentMethod;
            } else {
                return false;
            }
        }

        return parent::createToken();
    }

    /**
     * @return array
     */
    private function customerData()
    {
        return [
            'firstName' => array_get($this->input, 'first_name') ?: $this->contact()->first_name,
            'lastName' => array_get($this->input, 'last_name') ?: $this->contact()->last_name,
            'company' => $this->client()->name,
            'email' => $this->contact()->email,
            'phone' => $this->contact()->phone,
            'website' => $this->client()->website
        ];
    }

    /**
     * @param $customer
     *
     * @return mixed
     */
    public function creatingCustomer($customer)
    {
        $customer->token = $this->tokenResponse->customerId;

        return $customer;
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return PaymentMethod|null
     */
    protected function creatingPaymentMethod(PaymentMethod $paymentMethod)
    {
        $response = $this->tokenResponse;

        $paymentMethod->source_reference = $response->token;

        if ($this->isGatewayType(GATEWAY_TYPE_CREDIT_CARD)) {
            $paymentMethod->payment_type_id = $this->parseCardType($response->cardType);
            $paymentMethod->last4 = $response->last4;
            $paymentMethod->expiration = $response->expirationYear . '-' . $response->expirationMonth . '-01';
        } elseif ($this->isGatewayType(GATEWAY_TYPE_PAYPAL)) {
            $paymentMethod->email = $response->email;
            $paymentMethod->payment_type_id = PAYMENT_TYPE_PAYPAL;
        } else {
            return null;
        }

        return $paymentMethod;
    }

    /**
     * @param PaymentMethod $paymentMethod
     *
     * @return bool
     * @throws Exception
     */
    public function removePaymentMethod(PaymentMethod $paymentMethod)
    {
        parent::removePaymentMethod($paymentMethod);

        $response = $this->gateway()->deletePaymentMethod([
            'token' => $paymentMethod->source_reference
        ])->send();

        if ($response->isSuccessful()) {
            return true;
        } else {
            throw new Exception($response->getMessage());
        }
    }

    /**
     * @param $response
     * @param Payment $payment
     * @param $amount
     *
     * @return bool
     */
    protected function attemptVoidPayment($response, Payment $payment, $amount)
    {
        if ( ! parent::attemptVoidPayment($response, $payment, $amount)) {
            return false;
        }

        $data = $response->getData();

        if ($data instanceof \Braintree\Result\Error) {
            $error = $data->errors->deepAll()[0];
            if ($error && $error->code == 91506) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function createTransactionToken()
    {
        return $this->gateway()
                ->clientToken()
                ->send()
                ->getToken();
    }
}
