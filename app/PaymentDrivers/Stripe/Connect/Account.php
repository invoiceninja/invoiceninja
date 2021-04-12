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

use App\Exceptions\PaymentFailed;
use App\Http\Requests\Request;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Exception;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class Account
{

//https://stripe.com/docs/api/accounts/object?lang=php
	/**
	 * 
capabilities.acss_debit_payments
string
The status of the ACSS Direct Debits payments capability of the account, or whether the account can directly process ACSS Direct Debits charges.

capabilities.afterpay_clearpay_payments
string
The status of the Afterpay Clearpay capability of the account, or whether the account can directly process Afterpay Clearpay charges.

capabilities.au_becs_debit_payments
string
The status of the BECS Direct Debit (AU) payments capability of the account, or whether the account can directly process BECS Direct Debit (AU) charges.

capabilities.bacs_debit_payments
string
The status of the Bacs Direct Debits payments capability of the account, or whether the account can directly process Bacs Direct Debits charges.

capabilities.bancontact_payments
string
The status of the Bancontact payments capability of the account, or whether the account can directly process Bancontact charges.

capabilities.card_issuing
string
The status of the card issuing capability of the account, or whether you can use Issuing to distribute funds on cards

capabilities.card_payments
string
The status of the card payments capability of the account, or whether the account can directly process credit and debit card charges.

capabilities.cartes_bancaires_payments
string
The status of the Cartes Bancaires payments capability of the account, or whether the account can directly process Cartes Bancaires card charges in EUR currency.

capabilities.eps_payments
string
The status of the EPS payments capability of the account, or whether the account can directly process EPS charges.

capabilities.fpx_payments
string
The status of the FPX payments capability of the account, or whether the account can directly process FPX charges.

capabilities.giropay_payments
string
The status of the giropay payments capability of the account, or whether the account can directly process giropay charges.

capabilities.grabpay_payments
string
The status of the GrabPay payments capability of the account, or whether the account can directly process GrabPay charges.

capabilities.ideal_payments
string
The status of the iDEAL payments capability of the account, or whether the account can directly process iDEAL charges.

capabilities.jcb_payments
string
The status of the JCB payments capability of the account, or whether the account (Japan only) can directly process JCB credit card charges in JPY currency.

capabilities.legacy_payments
string
The status of the legacy payments capability of the account.

capabilities.oxxo_payments
string
The status of the OXXO payments capability of the account, or whether the account can directly process OXXO charges.

capabilities.p24_payments
string
The status of the P24 payments capability of the account, or whether the account can directly process P24 charges.

capabilities.sepa_debit_payments
string
The status of the SEPA Direct Debits payments capability of the account, or whether the account can directly process SEPA Direct Debits charges.

capabilities.sofort_payments
string
The status of the Sofort payments capability of the account, or whether the account can directly process Sofort charges.

capabilities.tax_reporting_us_1099_k
string
The status of the tax reporting 1099-K (US) capability of the account.

capabilities.tax_reporting_us_1099_misc
string
The status of the tax reporting 1099-MISC (US) capability of the account.

capabilities.transfers
string
The status of the transfers capability of the account, or whether your platform can transfer funds to the account.
	 */

///
// $stripe = new \Stripe\StripeClient(
//   'sk_test_4eC39HqLyjWDarjtT1zdp7dc'
// );
// $stripe->accounts->create([
//   'type' => 'custom',
//   'country' => 'US',
//   'email' => 'jenny.rosen@example.com',
//   'capabilities' => [
//     'card_payments' => ['requested' => true],
//     'transfers' => ['requested' => true],
//   ],
// ]);
///


//response

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



//then create the account link

// https://stripe.com/docs/api/account_links/create?lang=php
}