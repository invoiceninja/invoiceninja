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

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Payments\Payment;
use Checkout\Models\Payments\TokenSource;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

class CreditCard
{
    use Utilities;

    /**
     * @var CheckoutComPaymentDriver
     */
    public $checkout;

    public function __construct(CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;
    }

    /**
     * An authorization view for credit card.
     *
     * @param mixed $data
     * @return Factory|View
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
        $data['value'] = $this->checkout->convertToCheckoutAmount($data['total']['amount_with_fee'], $this->checkout->client->getCurrencyCode());
        $data['raw_value'] = $data['total']['amount_with_fee'];
        $data['customer_email'] = $this->checkout->client->present()->email;

        return render('gateways.checkout.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->checkout->init();

        $state = [
            'server_response' => json_decode($request->gateway_response),
            'value' => $request->value,
            'raw_value' => $request->raw_value,
            'currency' => $request->currency,
            'payment_hash' => $request->payment_hash,
            'reference' => $request->payment_hash,
            'client_id' => $this->checkout->client->id,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, $state);
        $this->checkout->payment_hash->save();

        if ($request->has('token') && !is_null($request->token) && $request->pay_with_token) {
            return $this->attemptPaymentUsingToken($request);
        }

        return $this->attemptPaymentUsingCreditCard($request);
    }

    private function attemptPaymentUsingToken(PaymentResponseRequest $request)
    {
        $method = new IdSource($this->checkout->payment_hash->data->token);

        return $this->completePayment($method, $request);
    }

    private function attemptPaymentUsingCreditCard(PaymentResponseRequest $request)
    {
        $checkout_response = $this->checkout->payment_hash->data->server_response;

        $method = new TokenSource(
            $checkout_response->token
        );

        return $this->completePayment($method, $request);
    }

    private function completePayment($method, PaymentResponseRequest $request)
    {
        $payment = new Payment($method, $this->checkout->payment_hash->data->currency);
        $payment->amount = $this->checkout->payment_hash->data->value;
        $payment->reference = $this->checkout->payment_hash->data->reference;

        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, ['checkout_payment_ref' => $payment]);
        $this->checkout->payment_hash->save();

        if ($this->checkout->client->currency()->code === 'EUR') {
            $payment->{'3ds'} = ['enabled' => true];

            $payment->{'success_url'} = route('payment_webhook', [
                'company_key' => $this->checkout->client->company->company_key,
                'company_gateway_id' => $this->checkout->company_gateway->hashed_id,
                'hash' => $this->checkout->payment_hash->hash,
            ]);
        }

        try {
            $response = $this->checkout->gateway->payments()->request($payment);

            if ($response->status == 'Authorized') {
                $this->checkout->confirmGatewayFee($request);

                return $this->processSuccessfulPayment($response);
            }

            if ($response->status == 'Pending') {
                $this->checkout->confirmGatewayFee($request);

                return $this->processPendingPayment($response);
            }

            if ($response->status == 'Declined') {
                $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

                return $this->processUnsuccessfulPayment($response);
            }
        } catch (CheckoutHttpException $e) {
            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            return $this->checkout->processInternallyFailedPayment($this->checkout, $e);
        }
    }
}
