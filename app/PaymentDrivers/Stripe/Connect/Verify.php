<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe\Connect;

use App\Exceptions\StripeConnectFailure;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Stripe\Customer;

class Verify
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function run()
    {
        $this->stripe->init();

        if ($this->stripe->stripe_connect && strlen($this->stripe->company_gateway->getConfigField('account_id')) < 1) {
            throw new StripeConnectFailure('Stripe Connect has not been configured');
        }

        $total_stripe_customers = 0;

        $starting_after = null;

        do {
            $customers = Customer::all(['limit' => 100, 'starting_after' => $starting_after], $this->stripe->stripe_connect_auth);

            $total_stripe_customers += count($customers->data);
            $starting_after = end($customers->data)['id'];
        } while ($customers->has_more);

        $stripe_customers = $this->stripe->company_gateway->client_gateway_tokens->map(function ($cgt) {
            $customer = Customer::retrieve($cgt->gateway_customer_reference, $this->stripe->stripe_connect_auth);

            return [
                'customer' => $cgt->gateway_customer_reference,
                'record' => $customer,
            ];
        });

        $data = [
            'stripe_customer_count' => $total_stripe_customers,
            'stripe_customers' => $stripe_customers,
        ];

        return response()->json($data, 200);
    }
}
