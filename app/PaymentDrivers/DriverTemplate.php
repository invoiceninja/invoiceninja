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

use App\Utils\Traits\MakesHash;


class YourGatewayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_STRIPE;

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request) 
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }


    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    	return $this->payment_method->yourRefundImplementationHere();
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation();
    }

    /**
     * Creates a payment record for the given
     * data array.
     *
     * @param  array $data   An array of payment attributes
     * @param  float $amount The amount of the payment
     * @return Payment       The payment object
     */
    public function createPaymentRecord($data, $amount): ?Payment
    {
        $payment = PaymentFactory::create($this->client->company_id, $this->client->user_id);
        $payment->client_id = $this->client->id;
        $payment->company_gateway_id = $this->company_gateway->id;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->gateway_type_id = $data['gateway_type_id'];
        $payment->type_id = $data['type_id'];
        $payment->currency_id = $this->client->getSetting('currency_id');
        $payment->date = Carbon::now();
        $payment->transaction_reference = $data['transaction_reference'];
        $payment->amount = $amount;
        $payment->save();

        return $payment->service()->applyNumber()->save();
    }

    
}
