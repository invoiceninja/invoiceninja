<?php

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Illuminate\Contracts\Container\BindingResolutionException;

use function Symfony\Component\String\b;

class CreditCard
{
    /**
     * @var MolliePaymentDriver
     */
    protected $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /**
     * Show the page for credit card payments.
     * 
     * @param array $data 
     * @return Factory|View 
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->mollie;

        return render('gateways.mollie.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        try {
            $payment = $this->mollie->gateway->payments->create([
                "amount" => [
                    "currency" => "USD",
                    "value" => "10.00"
                ],
                "description" => "Order #12345",
                "redirectUrl" => "https://webshop.example.org/order/12345/",
                "webhookUrl"  => "https://webshop.example.org/mollie-webhook/",
            ]);

            if ($payment->status === 'open') {
                return redirect($payment->getCheckoutUrl());
            }
        } catch (\Exception $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        dd($payment);
    }
}
