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

// {
//   "id": "ch_1H4lp42eZvKYlo2Ch5igaUwg",
//   "object": "charge",
//   "amount": 2000,
//   "amount_refunded": 0,
//   "application": null,
//   "application_fee": null,
//   "application_fee_amount": null,
//   "balance_transaction": "txn_19XJJ02eZvKYlo2ClwuJ1rbA",
//   "billing_details": {
//     "address": {
//       "city": null,
//       "country": null,
//       "line1": null,
//       "line2": null,
//       "postal_code": "45465",
//       "state": null
//     },
//     "email": null,
//     "name": null,
//     "phone": null
//   },
//   "calculated_statement_descriptor": null,
//   "captured": false,
//   "created": 1594724238,
//   "currency": "usd",
//   "customer": null,
//   "description": "My First Test Charge (created for API docs)",
//   "disputed": false,
//   "failure_code": null,
//   "failure_message": null,
//   "fraud_details": {},
//   "invoice": null,
//   "livemode": false,
//   "metadata": {},
//   "on_behalf_of": null,
//   "order": null,
//   "outcome": null,
//   "paid": true,
//   "payment_intent": null,
//   "payment_method": "card_1F8MLI2eZvKYlo2CvsyCzps2",
//   "payment_method_details": {
//     "card": {
//       "brand": "visa",
//       "checks": {
//         "address_line1_check": null,
//         "address_postal_code_check": "pass",
//         "cvc_check": null
//       },
//       "country": "US",
//       "exp_month": 12,
//       "exp_year": 2023,
//       "fingerprint": "Xt5EWLLDS7FJjR1c",
//       "funding": "credit",
//       "installments": null,
//       "last4": "4242",
//       "network": "visa",
//       "three_d_secure": null,
//       "wallet": null
//     },
//     "type": "card"
//   },
//   "receipt_email": null,
//   "receipt_number": null,
//   "receipt_url": "https://pay.stripe.com/receipts/acct_1032D82eZvKYlo2C/ch_1H4lp42eZvKYlo2Ch5igaUwg/rcpt_He3wuRQtzvT2Oi4OAYQSpajtmteo55J",
//   "refunded": false,
//   "refunds": {
//     "object": "list",
//     "data": [],
//     "has_more": false,
//     "url": "/v1/charges/ch_1H4lp42eZvKYlo2Ch5igaUwg/refunds"
//   },
//   "review": null,
//   "shipping": null,
//   "source_transfer": null,
//   "statement_descriptor": null,
//   "statement_descriptor_suffix": null,
//   "status": "succeeded",
//   "transfer_data": null,
//   "transfer_group": null,
//   "source": "tok_visa"
// }