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

    public function createPayment($data, $status = Payment::STATUS_COMPLETED): Payment
    {
        $payment = parent::createPayment($data, $status);

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : null;

        $payment->amount = $data['amount'];
        $payment->type_id = $data['payment_type'];
        $payment->transaction_reference = $data['payment_method'];
        $payment->client_contact_id = $client_contact_id;
        $payment->save();

        return $payment;
    }

    public function saveCard($state)
    {
        //some cards just can't be tokenized....
        if (!$state['payment_response']->source['id'])
            return;

        // [id] => src_hck5nsv3fljehbam2cvdm7fioa
        // [type] => card
        // [expiry_month] => 10
        // [expiry_year] => 2022
        // [scheme] => Visa
        // [last4] => 4242
        // [fingerprint] => 688192847DB9AE8A26C53776D036D5B8AD2CEAF1D5A8F5475F542B021041EFA1
        // [bin] => 424242
        // [card_type] => Credit
        // [card_category] => Consumer
        // [issuer] => JPMORGAN CHASE BANK NA
        // [issuer_country] => US
        // [product_id] => A
        // [product_type] => Visa Traditional
        // [avs_check] => S
        // [cvv_check] => Y
        // [payouts] => 1
        // [fast_funds] => d

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = (string)$state['payment_response']->source['expiry_month'];
        $payment_meta->exp_year = (string)$state['payment_response']->source['expiry_year'];
        $payment_meta->brand = (string)$state['payment_response']->source['scheme'];
        $payment_meta->last4 = (string)$state['payment_response']->source['last4'];
        $payment_meta->type = $this->payment_method;

        $company_gateway_token = new ClientGatewayToken();
        $company_gateway_token->company_id = $this->client->company->id;
        $company_gateway_token->client_id = $this->client->id;
        $company_gateway_token->token = $state['payment_response']->source['id'];
        $company_gateway_token->company_gateway_id = $this->company_gateway->id;
        $company_gateway_token->gateway_type_id = $state['payment_method_id'];
        $company_gateway_token->meta = $payment_meta;
        $company_gateway_token->save();

        if ($this->client->gateway_tokens->count() == 1) {
            $this->client->gateway_tokens()->update(['is_default' => 0]);

            $company_gateway_token->is_default = 1;
            $company_gateway_token->save();
        }
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
