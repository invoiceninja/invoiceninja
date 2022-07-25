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

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Authorize\AuthorizeCreditCard;
use App\PaymentDrivers\Authorize\AuthorizeCustomer;
use App\PaymentDrivers\Authorize\AuthorizePaymentMethod;
use App\PaymentDrivers\Authorize\RefundTransaction;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\GetMerchantDetailsRequest;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\controller\GetMerchantDetailsController;

/**
 * Class BaseDriver.
 */
class AuthorizePaymentDriver extends BaseDriver
{
    public $merchant_authentication;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public static $methods = [
        GatewayType::CREDIT_CARD => AuthorizeCreditCard::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_AUTHORIZE;

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    public function getClientRequiredFields(): array
    {

        $fields = [];

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }


        $data = [
            ['name' => 'client_name', 'label' => ctrans('texts.name'), 'type' => 'text', 'validation' => 'required|min:2'],
            ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required|email:rfc'],
            ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'select', 'validation' => 'required'],
        ];

        return array_merge($fields, $data);

    }

    public function authorizeView($payment_method)
    {
        return (new AuthorizePaymentMethod($this))->authorizeView();
    }

    public function authorizeResponse($request)
    {
        return (new AuthorizePaymentMethod($this))->authorizeResponseView($request);
    }

    public function processPaymentView($data)
    {
        return $this->payment_method->processPaymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->processPaymentResponse($request);
    }

    public function refund(Payment $payment, $refund_amount, $return_client_response = false)
    {
        return (new RefundTransaction($this))->refundTransaction($payment, $refund_amount);
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->init();

        $this->setPaymentMethod($cgt->gateway_type_id);

        return $this->payment_method->tokenBilling($cgt, $payment_hash);
    }

    public function init()
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->merchant_authentication = new MerchantAuthenticationType();
        $this->merchant_authentication->setName($this->company_gateway->getConfigField('apiLoginId'));
        $this->merchant_authentication->setTransactionKey($this->company_gateway->getConfigField('transactionKey'));

        return $this;
    }

    public function getPublicClientKey()
    {
        $request = new GetMerchantDetailsRequest();
        $request->setMerchantAuthentication($this->merchant_authentication);

        $controller = new GetMerchantDetailsController($request);
        $response = $controller->executeWithApiResponse($this->mode());

        return $response->getPublicClientKey();
    }

    public function mode()
    {
        if ($this->company_gateway->getConfigField('testMode')) {
            return  ANetEnvironment::SANDBOX;
        }

        return $env = ANetEnvironment::PRODUCTION;
    }

    public function findClientGatewayRecord() :?ClientGatewayToken
    {
        return ClientGatewayToken::where('client_id', $this->client->id)
                                 ->where('company_gateway_id', $this->company_gateway->id)
                                 ->first();
    }

    /**
     * Detach payment method from Authorize.net.
     *
     * @param ClientGatewayToken $token
     * @return void
     */
    public function detach(ClientGatewayToken $token)
    {
        return (new AuthorizePaymentMethod($this))->deletePaymentProfile($token->gateway_customer_reference, $token->token);
    }

    public function import()
    {
        return (new AuthorizeCustomer($this))->importCustomers();
    }
}
