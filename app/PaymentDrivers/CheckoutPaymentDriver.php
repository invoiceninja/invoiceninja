<?php

namespace App\PaymentDrivers;

use App\PaymentDrivers\Actions\CheckoutActions;

class CheckoutPaymentDriver extends BasePaymentDriver
{
    use CheckoutActions;

    public $config;

    public $view = 'gateways.checkout.credit_card';

    public function __construct()
    {
        $this->config = json_decode(config('ninja.testvars.checkout'));
    }

    public function createTransactionToken($amount, $currency)
    {
        // if ($this->invoice()->getCurrencyCode() == 'BHD') {
        //     $amount = $this->invoice()->getRequestedAmount() / 10;
        // } elseif ($this->invoice()->getCurrencyCode() == 'KWD') {
        //     $amount = $this->invoice()->getRequestedAmount() * 10;
        // } elseif ($this->invoice()->getCurrencyCode() == 'OMR') {
        //     $amount = $this->invoice()->getRequestedAmount();
        // } else
        //     $amount = $this->invoice()->getRequestedAmount();
        // }

        if ($currency == 'BHD') {
            $amount = $amount / 10;
        }

        if ($currency == 'KWD') {
            $amount = $amount * 10;
        }

        // $response = $this->gateway()->purchase([
        //     'amount' => $amount,
        //     'currency' => $this->client->getCurrencyCode(),
        // ])->send();

        // if ($response->isRedirect()) {
        //     $token = $response->getTransactionReference();

        //     session()->flash('transaction_reference', $token);


        //     // On each request, session()->flash() || sesion('', value) || session[name] ||session->flash(key, value)

        //     return $token;
        // }

        // return false;
    }

    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this;
        $data['contact'] = $this->getContact();
        $data['payment_token'] = $this->createTransactionToken($data['amount'], $this->getContact()->client->getCurrencyCode());

        return render($this->view, $data);
    }
}
