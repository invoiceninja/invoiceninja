<?php

namespace App\PaymentDrivers\Actions;

trait CheckoutActions
{
    public function getPublicKey()
    {
        return $this->config->publicApiKey;
    }
}
