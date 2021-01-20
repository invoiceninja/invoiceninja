<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Stripe;


use App\PaymentDrivers\StripePaymentDriver;

class Mobile
{
    /**
     * @var StripePaymentDriver
     */
    private $stripe;

    public function __construct(StripePaymentDriver $stripePaymentDriver)
    {
        $this->stripe = $stripePaymentDriver;
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.mobile.pay', $data);
    }
}
