<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Stripe;

use App\PaymentDrivers\StripePaymentDriver;

class CreditCard
{
    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Authorises a credit card for future use.
     *
     * @param  array  $data Array of variables needed for the view.
     * 
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function authorizeView(array $data)
    {
        $intent['intent'] = $this->stripe->getSetupIntent();

        return render('gateways.stripe.add_credit_card', array_merge($data, $intent));
    }
}