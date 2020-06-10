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

namespace App\PaymentDrivers\CheckoutCom;

trait Utilities
{
    public function getPublishableKey()
    {
        // This doesn't work since $gateway->getPublishableKey is looking for 'publishableKey' 
        // but we use 'publicApiKey' for Checkout.com in .env file.

        // This is dummy implementation and it needs to be fixed.

        return $this->company_gateway->getConfig()->publicApiKey;
    }
}
