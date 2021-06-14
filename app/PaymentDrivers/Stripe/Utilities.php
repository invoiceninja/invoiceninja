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

trait Utilities
{
    public function convertFromStripeAmount($amount, $precision)
    {
        return $amount / pow(10, $precision);
    }

    public function convertToStripeAmount($amount, $precision)
    {
        return (int)($amount * pow(10, $precision));
    }
}
