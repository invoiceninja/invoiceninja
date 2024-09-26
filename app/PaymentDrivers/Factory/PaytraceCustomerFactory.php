<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Factory;

use App\Models\Company;

class PaytraceCustomerFactory
{
    public function convertToNinja($customer, Company $company): array
    {
        return
            collect([
                'name' => $customer->billing_address->name ?? $customer->shipping_address->name,
                'contacts' => [
                    [
                        'first_name' => $customer->billing_address->name ?? $customer->shipping_address->name,
                        'last_name' => '',
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                    ]
                ],
                'currency_id' => $company->settings->currency_id,
                'address1' => $customer->billing_address->street_address,
                'address2' => $customer->billing_address->street_address2,
                'city' => $customer->billing_address->city,
                'state' => $customer->billing_address->state,
                'postal_code' => $customer->billing_address->zip,
                'country_id' => '840',
                'shipping_address1' => $customer->shipping_address->street_address,
                'shipping_address2' => $customer->shipping_address->street_address2,
                'shipping_city' => $customer->shipping_address->city,
                'shipping_state' => $customer->shipping_address->state,
                'shipping_postal_code' => $customer->shipping_address->zip,
                'shipping_country_id' => '840',
                'settings' => [
                    'currency_id' => $company->settings->currency_id,
                ],
                'card' => [
                    'token' => $customer->customer_id,
                    'last4' => $customer->credit_card->masked_number,
                    'expiry_month' => $customer->credit_card->expiration_month,
                    'expiry_year' => $customer->credit_card->expiration_year,
                ],
            ])
            ->toArray();

    }

}
