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

use App\Models\ClientGatewayToken;
use App\PaymentDrivers\StripePaymentDriver;

class Charge
{
    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    /**
     * Create a charge against a payment method
     * @return bool success/failure
     */
    public function tokenBilling(ClientGatewayToken $cgt, $amount, ?Invoice $invoice)
    {

        if($invoice)
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->stripe->client->present()->name()}";
        else
            $description = "Payment with no invoice for amount {$amount} for client {$this->stripe->client->present()->name()}";

        $response = $this->stripe->charges->create([
          'amount' => $this->stripe->convertToStripeAmount($amount, $this->stripe->client->currency()->precision),
          'currency' => $this->stripe->client->getCurrencyCode(),
          'source' => $cgt->token,
          'description' => $description,
        ]);

        info(print_r($response,1));
    }

}
