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

namespace App\Http\Controllers\SubscriptionsV4;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Subscription;
use App\Services\ClientPortal\InstantPayment;

class StripeController
{
    public function intent(Subscription $subscription)
    {
        $stripe = $subscription->company->company_gateways
            ->where('gateway_key', 'd14dd26a37cecc30fdd65700bfb55b23')
            ->first();

        $driver = $stripe->driver();

        $intent = $driver->createPaymentIntent([
            'automatic_payment_methods' => ['enabled' => true],
            'amount' => 1000,
            'currency' => 'eur',
        ]);

        return response()->json([
            'client_secret' => $intent->client_secret,
        ]);
    }

    public function charge(Subscription $subscription, Request $request)
    {
        // Here we can access the "key" and "context" & "gateway_response".

        return response()->noContent(
            200
        );
    }
}
