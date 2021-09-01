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

namespace App\PaymentDrivers\RecebeAqui;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\RecebeAquiPaymentDriver;

class CreditCard
{
    public RecebeAquiPaymentDriver $driver;

    public function __construct(RecebeAquiPaymentDriver $driver)
    {
        $this->driver = $driver;
    }

    public function authorizeView(array $data)
    {
        return render('gateways.recebeaqui.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        return render('gateways.recebeaqui.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        return null;
    }
}
