<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\BaseDriver;
use App\PaymentDrivers\CheckoutCom\Utilities;
use App\Utils\Traits\SystemLogTrait;
use Checkout\CheckoutApi;
use Checkout\Library\Exceptions\CheckoutHttpException;

class CheckoutComPaymentDriver extends BaseDriver
{
    use SystemLogTrait, Utilities;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /* Gateway capabilities */
    public $refundable = true;

    /* Token billing */
    public $token_billing = true;

    /* Authorise payment methods */
    public $can_authorise_credit_card = true;

    /**
     * @var \Checkout\CheckoutApi;
     */
    public $gateway;

    public $payment_method; //the gateway type id

    /**
     * @var \App\Models\PaymentHash
     */
    public $payment_hash;

    public static $methods = [
        GatewayType::CREDIT_CARD => \App\PaymentDrivers\CheckoutCom\CreditCard::class,
    ];

    /**
     * Returns the default gateway type.
     */
    public function gatewayTypes()
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

    /** 
     * Since with Checkout.com we handle only credit cards, this method should be empty.
     * @param $string payment_method string
     */
    public function setPaymentMethod($payment_method = null)
    {
        // At the moment Checkout.com payment 
        // driver only supports payments using credit card.

        $class = self::$methods[GatewayType::CREDIT_CARD];

        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Initialize the checkout payment driver
     * @return $this
     */
    public function init()
    {
        $config = [
            'secret' =>  $this->company_gateway->getConfigField('secretApiKey'),
            'public' =>  $this->company_gateway->getConfigField('publicApiKey'),
            'sandbox' => $this->company_gateway->getConfigField('testMode'),
        ];

        $this->gateway = new CheckoutApi($config['secret'], $config['sandbox'], $config['public']);

        return $this;
    }

    /**
     * Process different view depending on payment type
     * @param  int      $gateway_type_id    The gateway type
     * @return string                       The view string
     */
    public function viewForType($gateway_type_id)
    {
        // At the moment Checkout.com payment 
        // driver only supports payments using credit card.

        return 'gateways.checkout.credit_card.pay';
    }

    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    /**
     * Payment View
     * 
     * @param  array  $data Payment data array
     * @return view         The payment view
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Process the payment response
     * 
     * @param  Request $request The payment request
     * @return view             The payment response view
     */
    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function storePaymentMethod(array $data)
    {
        return $this->storeGatewayToken($data);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $checkout_payment = new \Checkout\Models\Payments\Refund($payment->transaction_reference);

        try {
            $refund = $this->gateway->payments()->refund($checkout_payment);
            $checkout_payment = $this->gateway->payments()->details($refund->id);

            $response = ['refund_response' => $refund, 'checkout_payment_fetch' => $checkout_payment];

            return [
                'transaction_reference' => $refund->action_id,
                'transaction_response' => json_encode($response),
                'success' => $checkout_payment->status == 'Refunded',
                'description' => $checkout_payment->status,
                'code' => $checkout_payment->http_code,
            ];
        } catch (CheckoutHttpException $e) {
            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }
}
