<?php

namespace App\PaymentDrivers\Stripe;

trait Utilities
{
    public function convertFromStripeAmount($amount, $precision)
    {
        return $amount / pow(10, $precision);
    }

    public function convertToStripeAmount($amount, $precision)
    {
        return $amount * pow(10, $precision);
    }
}
