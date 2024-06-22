<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * @deprecated 5.9
 */
class WePayPaymentDriver extends BaseDriver
{
    use MakesHash;

    /* Does this gateway support refunds? */
    public $refundable = true;

    /* Does this gateway support token billing? */
    public $token_billing = true;

    /* Does this gateway support authorizations? */
    public $can_authorise_credit_card = true;

    /* Initialized gateway */
    public $wepay;

    /* Initialized payment method */
    public $payment_method;

    /* Maps the Payment Gateway Type - to its implementation */
    public static $methods = [
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_WEPAY;

    public function init()
    {
        throw new \Exception("Gateway no longer supported", 500);


        // return $this;
    }

    /**
     * Return the gateway types that have been enabled
     *
     * @return array
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    /**
     * Setup the gateway
     *
     * @param  array $data user_id + company
     * @return void
     */
    public function setup(array $data)
    {
    }

    /**
     * Set the payment method
     *
     * @param int $payment_method_id Alias of GatewayType
     */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        $this->init();

        $data['gateway'] = $this->wepay;
        $client = $data['client'];
        $contact = $client->primary_contact()->first() ? $client->primary_contact()->first() : $client->contacts->first();
        $data['contact'] = $contact;

        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        $this->init();

        return $this->payment_method->authorizeResponse($request);
    }

    public function verificationView(ClientGatewayToken $cgt)
    {
        $this->init();

        return $this->payment_method->verificationView($cgt);
    }

    public function processVerification(Request $request, ClientGatewayToken $cgt)
    {
        $this->init();

        return $this->payment_method->processVerification($request, $cgt);
    }

    public function processPaymentView(array $data)
    {
        $this->init();

        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        $this->init();

        return $this->payment_method->paymentResponse($request);
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->init();
        $this->setPaymentMethod($cgt->gateway_type_id);
        $this->setPaymentHash($payment_hash);

        return $this->payment_method->tokenBilling($cgt, $payment_hash);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
        $this->init();
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

    }

    public function detach(ClientGatewayToken $token): bool
    {
        return true;
    }

    public function getClientRequiredFields(): array
    {
        $fields = [
            ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'],
            ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required'],
        ];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_name) {
            $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_contact_email) {
            $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
            //            $fields[] = ['name' => 'client_address_line_2', 'label' => ctrans('texts.address2'), 'type' => 'text', 'validation' => 'nullable'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            //            $fields[] = ['name' => 'client_shipping_address_line_2', 'label' => ctrans('texts.shipping_address2'), 'type' => 'text', 'validation' => 'sometimes'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }



        if ($this->company_gateway->require_custom_value1) {
            $fields[] = ['name' => 'client_custom_value1', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client1'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value2) {
            $fields[] = ['name' => 'client_custom_value2', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client2'), 'type' => 'text', 'validation' => 'required'];
        }


        if ($this->company_gateway->require_custom_value3) {
            $fields[] = ['name' => 'client_custom_value3', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client3'), 'type' => 'text', 'validation' => 'required'];
        }


        if ($this->company_gateway->require_custom_value4) {
            $fields[] = ['name' => 'client_custom_value4', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client4'), 'type' => 'text', 'validation' => 'required'];
        }



        return $fields;
    }
}
