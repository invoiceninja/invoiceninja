<?php

namespace App\Ninja\PaymentDrivers;

use Braintree\Customer;
use Exception;
use Session;
use App\Models\GatewayType;

class BraintreePaymentDriver extends BasePaymentDriver
{
    protected $customerReferenceParam = 'customerId';
    protected $sourceReferenceParam = 'paymentMethodToken';
    public $canRefundPayments = true;

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

    public function tokenize()
    {
        return true;
    }

    public function startPurchase($input = false, $sourceId = false)
    {
        $data = parent::startPurchase($input, $sourceId);

        if ($this->isGatewayType(GATEWAY_TYPE_PAYPAL)) {
            /*
            if ( ! $sourceId || empty($input['device_data'])) {
                throw new Exception();
            }

            Session::put($this->invitation->id . 'device_data', $input['device_data']);
            */

            $data['details'] = ! empty($input['device_data']) ? json_decode($input['device_data']) : false;
        }

        return $data;
    }

    protected function checkCustomerExists($customer)
    {
        if (! parent::checkCustomerExists($customer)) {
            return false;
        }

        $customer = $this->gateway()->findCustomer($customer->token)
            ->send()
            ->getData();

        return $customer instanceof Customer;
    }

    protected function paymentUrl($gatewayTypeAlias)
    {
        $url = parent::paymentUrl($gatewayTypeAlias);

        if (GatewayType::getIdFromAlias($gatewayTypeAlias) === GATEWAY_TYPE_PAYPAL) {
            $url .= '#braintree_paypal';
        }

        return $url;
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        $deviceData = array_get($this->input, 'device_data') ?: Session::get($this->invitation->id . 'device_data');

        if ($deviceData) {
            $data['device_data'] = $deviceData;
        }

        if ($this->isGatewayType(GATEWAY_TYPE_PAYPAL, $paymentMethod)) {
            $data['ButtonSource'] = 'InvoiceNinja_SP';
        }

        if (! $paymentMethod && ! empty($this->input['sourceToken'])) {
            $data['token'] = $this->input['sourceToken'];
        }

        return $data;
    }

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

    private function customerData()
    {
        return [
            'firstName' => array_get($this->input, 'first_name') ?: $this->contact()->first_name,
            'lastName' => array_get($this->input, 'last_name') ?: $this->contact()->last_name,
            'company' => $this->client()->name,
            'email' => $this->contact()->email,
            'phone' => $this->contact()->phone,
            'website' => $this->client()->website,
        ];
    }

    public function creatingCustomer($customer)
    {
        $customer->token = $this->tokenResponse->customerId;

        return $customer;
    }

    protected function creatingPaymentMethod($paymentMethod)
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

    public function removePaymentMethod($paymentMethod)
    {
        parent::removePaymentMethod($paymentMethod);

        $response = $this->gateway()->deletePaymentMethod([
            'token' => $paymentMethod->source_reference,
        ])->send();

        if ($response->isSuccessful()) {
            return true;
        } else {
            throw new Exception($response->getMessage());
        }
    }

    protected function attemptVoidPayment($response, $payment, $amount)
    {
        if (! parent::attemptVoidPayment($response, $payment, $amount)) {
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

    public function createTransactionToken()
    {
        return $this->gateway()
                ->clientToken()
                ->send()
                ->getToken();
    }
}
