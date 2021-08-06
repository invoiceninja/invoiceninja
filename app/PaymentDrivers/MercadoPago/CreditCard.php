<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\MercadoPago;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\MercadoPagoPaymentDriver;

class CreditCard
{
    public MercadoPagoPaymentDriver $driver;

    public function __construct(MercadoPagoPaymentDriver $driver)
    {
        $this->driver = $driver;
    }

    public function authorizeView(array $data)
    {
        return render('gateways.mercadopago.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        /*$payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => ctrans('texts.invoices') . ': ' . collect($data['invoices'])->pluck('invoice_number'), // TODO: More meaningful description.
        ];

        $payment_intent_data['setup_future_usage'] = 'off_session';

        $data['gateway'] = $this->stripe;*/

        return render('gateways.mercadopago.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        return null;
    }
}
