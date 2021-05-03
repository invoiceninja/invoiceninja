<?php


namespace App\PaymentDrivers\Braintree;


use App\PaymentDrivers\BraintreePaymentDriver;

class PayPal
{
    /**
     * @var BraintreePaymentDriver
     */
    private $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    /**
     * Credit card payment page.
     *
     * @param array $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->braintree;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return render('gateways.braintree.paypal.pay', $data);
    }
}
