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
    public static function link(string $account_id): \Stripe\AccountLink
    {
        $stripe = new \Stripe\StripeClient(
            config('ninja.ninja_stripe_key')
        );

        return $stripe->accountLinks->create([
            'account' => $account_id,
            'refresh_url' => 'http://ninja.test:8000/stripe_connect/reauth',
            'return_url' => 'http://ninja.test:8000/stripe_connect/return',
            'type' => 'account_onboarding',
        ]);
    }

/*** If this is a new account (ie there is no account_id in company_gateways.config, the we need to create an account as below.

///
// $stripe = new \Stripe\StripeClient(
//   'sk_test_4eC39HqLyjWDarjtT1zdp7dc'
// );
// $stripe->accounts->create([
//   'type' => 'standard',
//   'country' => 'US', //if we have it - inject
//   'email' => 'jenny.rosen@example.com', //if we have it - inject
// ]);
///


//response

//******************* We should store the 'id' as a property in the config with the key `account_id`

/**
 * {
  "id": "acct_1032D82eZvKYlo2C",
  "object": "account",
  "business_profile": {
    "mcc": null,
    "name": "Stripe.com",
    "product_description": null,
    "support_address": null,
    "support_email": null,
    "support_phone": null,
    "support_url": null,
    "url": null
  },
  "capabilities": {
    "card_payments": "active",
    "transfers": "active"
  },
  "charges_enabled": false,
  "country": "US",
  "default_currency": "usd",
  "details_submitted": false,
  "email": "site@stripe.com",
  "metadata": {},
  "payouts_enabled": false,
  "requirements": {
    "current_deadline": null,
    "currently_due": [
      "business_profile.product_description",
      "business_profile.support_phone",
      "business_profile.url",
      "external_account",
      "tos_acceptance.date",
      "tos_acceptance.ip"
    ],
    "disabled_reason": "requirements.past_due",
    "errors": [],
    "eventually_due": [
      "business_profile.product_description",
      "business_profile.support_phone",
      "business_profile.url",
      "external_account",
      "tos_acceptance.date",
      "tos_acceptance.ip"
    ],
    "past_due": [],
    "pending_verification": []
  },
  "settings": {
    "bacs_debit_payments": {},
    "branding": {
      "icon": null,
      "logo": null,
      "primary_color": null,
      "secondary_color": null
    },
    "card_issuing": {
      "tos_acceptance": {
        "date": null,
        "ip": null
      }
    },
    "card_payments": {
      "decline_on": {
        "avs_failure": true,
        "cvc_failure": false
      },
      "statement_descriptor_prefix": null
    },
    "dashboard": {
      "display_name": "Stripe.com",
      "timezone": "US/Pacific"
    },
    "payments": {
      "statement_descriptor": null,
      "statement_descriptor_kana": null,
      "statement_descriptor_kanji": null
    },
    "payouts": {
      "debit_negative_balances": true,
      "schedule": {
        "delay_days": 7,
        "interval": "daily"
      },
      "statement_descriptor": null
    },
    "sepa_debit_payments": {}
  },
  "type": "standard"
}

 */


//At this stage we have an account, so we need to generate the account link
//then create the account link

// now we start the stripe onboarding flow
// https://stripe.com/docs/api/account_links/object
//
/**
 * $stripe = new \Stripe\StripeClient(
  'sk_test_4eC39HqLyjWDarjtT1zdp7dc'
);
$stripe->accountLinks->create([
  'account' => 'acct_1032D82eZvKYlo2C',
  'refresh_url' => 'https://example.com/reauth',
  'return_url' => 'https://example.com/return',
  'type' => 'account_onboarding',
]);
 */

/**
 * Response =
 * {
  "object": "account_link",
  "created": 1618869558,
  "expires_at": 1618869858,
  "url": "https://connect.stripe.com/setup/s/9BhFaPdfseRF"
}
 */

//The users account may not be active yet, we need to pull the account back and check for the property `charges_enabled`
//
//


// What next?
//
// Now we need to create a superclass of the StripePaymentDriver, i believe the only thing we need to change is the way we initialize the gateway..

/**
 *
\Stripe\Stripe::setApiKey("{{PLATFORM_SECRET_KEY}}"); <--- platform secret key  = Invoice Ninja secret key
\Stripe\Customer::create(
  ["email" => "person@example.edu"],
  ["stripe_account" => "{{CONNECTED_STRIPE_ACCOUNT_ID}}"] <------ company_gateway.config.account_id
);


 */

}
