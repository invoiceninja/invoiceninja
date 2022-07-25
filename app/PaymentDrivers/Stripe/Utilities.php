<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe;

trait Utilities
{
    /*Helpers for currency conversions, NOTE* for some currencies we need to change behaviour */
    public function convertFromStripeAmount($amount, $precision, $currency)
    {
        if (in_array($currency->code, ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'])) {
            return $amount;
        }

        return $amount / pow(10, $precision);
    }

    public function convertToStripeAmount($amount, $precision, $currency)
    {
        if (in_array($currency->code, ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'])) {
            return $amount;
        }

        return round(($amount * pow(10, $precision)), 0);
    }
}
