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

namespace App\PaymentDrivers\CheckoutCom;

use App\Models\PaymentHash;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Payments\Payment;
use Checkout\Models\Payments\TokenSource;

class CreditCard
{
    use Utilities;

    /**
     * @var \App\PaymentDrivers\CheckoutComPaymentDriver
     */
    public $checkout;

    /**
     * @var \App\Models\PaymentHash
     */
    private $payment_hash;

    public function __construct(CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;
    }

    /**
     * An authorization view for credit card.
     * 
     * @param mixed $data 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View 
     */
    public function authorizeView($data)
    {
        return render('gateways.checkout.credit_card.authorize');
    }

    /**
     * Checkout.com supports doesn't support direct authorization of the credit card.
     * Token can be saved after the first (successful) purchase.
     * 
     * @param mixed $data 
     * @return void 
     */
    public function authorizeResponse($data)
    {
        return;
    }

    public function paymentView($data)
    {
        $data['gateway'] = $this->checkout;
        $data['company_gateway'] = $this->checkout->company_gateway;
        $data['client'] = $this->checkout->client;
        $data['currency'] = $this->checkout->client->getCurrencyCode();
        $data['value'] = $this->checkout->convertToCheckoutAmount($data['amount_with_fee'], $this->checkout->client->getCurrencyCode());
        $data['raw_value'] = $data['amount_with_fee'];
        $data['customer_email'] = $this->checkout->client->present()->email;

        return render('gateways.checkout.credit_card.pay', $data);
    }

    public function paymentResponse($request)
    {
        $this->checkout->init();

        $state = [
            'server_response' => json_decode($request->gateway_response),
            'value' => $request->value,
            'raw_value' => $request->raw_value,
            'currency' => $request->currency,
            'payment_hash' => $request->payment_hash,
            'reference' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$request->payment_hash])->first();

        $payment_hash->data = array_merge((array) $payment_hash->data, $state);
        $payment_hash->save();

        $this->payment_hash = $payment_hash;

        if ($request->has('token') && !is_null($request->token)) {
            return $this->attemptPaymentUsingToken();
        }

        return $this->attemptPaymentUsingCreditCard();
    }

    private function attemptPaymentUsingToken()
    {
        $method = new IdSource($this->payment_hash->data->token);

        return $this->completePayment($method);
    }

    private function attemptPaymentUsingCreditCard()
    {
        $checkout_response = $this->payment_hash->data->server_response;

        $method = new TokenSource(
            $checkout_response->cardToken
        );

        return $this->completePayment($method);
    }

    private function completePayment($method, $enable_3ds = false)
    {
        // TODO: confirmGatewayFee & unwind

        $payment = new Payment($method, $this->payment_hash->data->currency);
        $payment->amount = $this->payment_hash->data->value;
        $payment->reference = $this->payment_hash->data->reference;

        if ($this->checkout->client->currency()->code === 'EUR' && $enable_3ds) {
            $payment->{'3ds'} = ['enabled' => true];
        }

        try {
            $response = $this->checkout->gateway->payments()->request($payment);

            if ($response->status == 'Authorized') {
                return $this->processSuccessfulPayment($response);
            }

            if ($response->status == 'Pending') {
                return $this->processPendingPayment($response);
            }

            if ($response->status == 'Declined') {
                return $this->processUnsuccessfulPayment($response);
            }
        } catch (\Checkout\Library\Exceptions\CheckoutHttpException $e) {
            return $this->processInternallyFailedPayment($e);
        }
    }
}
