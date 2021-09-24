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

namespace App\PaymentDrivers\Mollie;

use App\Http\Requests\Request;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;

class Bancontact implements MethodInterface
{
    protected MolliePaymentDriver $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    public function authorizeView(array $data) { }

    public function authorizeResponse(Request $request) { }

    public function paymentView(array $data) { }

    public function paymentResponse(PaymentResponseRequest $request) { }
}
