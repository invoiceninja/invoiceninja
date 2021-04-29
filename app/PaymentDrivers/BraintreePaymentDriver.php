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

namespace App\PaymentDrivers;


use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\Braintree\CreditCard;
use Illuminate\Http\Request;

class BraintreePaymentDriver extends BaseDriver
{
    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    /**
     * @var \Braintree\Gateway;
     */
    public $gateway;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::PAYPAL,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_BRAINTREE;

    public function init(): void
    {
        $this->gateway = new \Braintree\Gateway([
            'environment' => $this->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production',
            'merchantId' => $this->company_gateway->getConfigField('merchantId'),
            'publicKey' => $this->company_gateway->getConfigField('publicKey'),
            'privateKey' => $this->company_gateway->getConfigField('privateKey'),
        ]);
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
            GatewayType::PAYPAL,
        ];
    }

    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function findOrCreateCustomer()
    {
        $existing = ClientGatewayToken::query()
            ->where('company_gateway_id', $this->company_gateway->id)
            ->where('client_id', $this->client->id)
            ->first();

        if ($existing) {
            return $this->gateway->customer()->find($existing->gateway_customer_reference);
        }

        $result =  $this->gateway->customer()->create([
            'firstName' => $this->client->present()->name,
            'email' => $this->client->present()->email,
            'phone' => $this->client->present()->phone,
        ]);

        if ($result->success) {
            return $result->customer;
        }
    }
}
