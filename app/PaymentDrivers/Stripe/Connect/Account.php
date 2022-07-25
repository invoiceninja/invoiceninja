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

namespace App\PaymentDrivers\Stripe\Connect;

class Account
{
    /**
     * @throws \Stripe\Exception\ApiErrorException
     */
    public static function create(array $payload): \Stripe\Account
    {
        $stripe = new \Stripe\StripeClient(
            config('ninja.ninja_stripe_key')
        );

        return $stripe->accounts->create([
            'type' => 'standard',
            'country' => $payload['country'],
            'email' => $payload['email'],
        ]);
    }

    /**
     * @throws \Stripe\Exception\ApiErrorException
     */
    public static function link(string $account_id, string $token): \Stripe\AccountLink
    {
        $stripe = new \Stripe\StripeClient(
            config('ninja.ninja_stripe_key')
        );

        return $stripe->accountLinks->create([
            'account' => $account_id,
            'refresh_url' => route('stripe_connect.initialization', $token),
            'return_url' => route('stripe_connect.return'),
            'type' => 'account_onboarding',
        ]);
    }
}
