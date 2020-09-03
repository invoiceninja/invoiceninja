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

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;

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
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {

        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = sInvoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->first();

        if($invoice)
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->stripe->client->present()->name()}";
        else
            $description = "Payment with no invoice for amount {$amount} for client {$this->stripe->client->present()->name()}";

        $this->stripe->init();

        $local_stripe = new \Stripe\StripeClient(
            $this->stripe->company_gateway->getConfigField('apiKey')
        );

        $response = null;

        try {

            $response = $local_stripe->paymentIntents->create([
              'amount' => $this->stripe->convertToStripeAmount($amount, $this->stripe->client->currency()->precision),
              'currency' => $this->stripe->client->getCurrencyCode(),
              'payment_method' => $cgt->token,
              'customer' => $cgt->gateway_customer_reference,
              'confirm' => true,
              'description' => $description,
            ]);

            SystemLogger::dispatch($response, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->stripe->client);


        } catch(\Stripe\Exception\CardException $e) {
          // Since it's a decline, \Stripe\Exception\CardException will be caught
          
          $data = [
            'status' => $e->getHttpStatus(),
            'error_type' => $e->getError()->type,
            'error_code' => $e->getError()->code,
            'param' => $e->getError()->param,
            'message' => $e->getError()->message
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        } catch (\Stripe\Exception\RateLimitException $e) {
          // Too many requests made to the API too quickly
 
          $data = [
            'status' => '',
            'error_type' => '',
            'error_code' => '',
            'param' => '',
            'message' => 'Too many requests made to the API too quickly'
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        } catch (\Stripe\Exception\InvalidRequestException $e) {
          // Invalid parameters were supplied to Stripe's API
          // 
          $data = [
            'status' => '',
            'error_type' => '',
            'error_code' => '',
            'param' => '',
            'message' => 'Invalid parameters were supplied to Stripe\'s API'
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        } catch (\Stripe\Exception\AuthenticationException $e) {
          // Authentication with Stripe's API failed
          
          $data = [
            'status' => '',
            'error_type' => '',
            'error_code' => '',
            'param' => '',
            'message' => 'Authentication with Stripe\'s API failed'
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        } catch (\Stripe\Exception\ApiConnectionException $e) {
          // Network communication with Stripe failed
          
          $data = [
            'status' => '',
            'error_type' => '',
            'error_code' => '',
            'param' => '',
            'message' => 'Network communication with Stripe failed'
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        } catch (\Stripe\Exception\ApiErrorException $e) {
          
          $data = [
            'status' => '',
            'error_type' => '',
            'error_code' => '',
            'param' => '',
            'message' => 'API Error'
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);

        } catch (Exception $e) {
          // Something else happened, completely unrelated to Stripe
          // 
         $data = [
            'status' => '',
            'error_type' => '',
            'error_code' => '',
            'param' => '',
            'message' => $e->getMessage(),
          ];

          SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->stripe->client);
        } 

        if(!$response)
            return false;

        $payment_method_type = $response->charges->data[0]->payment_method_details->card->brand;
        //info($payment_method_type);

        $data = [
            'gateway_type_id' => $cgt->gateway_type_id,
            'type_id' => $this->transformPaymentTypeToConstant($payment_method_type),
            'transaction_reference' => $response->charges->data[0]->id,
        ];

        $payment = $this->stripe->createPaymentRecord($data, $amount);
        $payment->meta = $cgt->meta;
        $payment->save();

        $this->stripe->attachInvoices($payment, $payment_hash);

        $payment->service()->updateInvoicePayment($payment_hash);

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        return $payment;
    }


    private function formatGatewayResponse($data, $vars)
    {
        $response = $data['response'];

        return [
            'transaction_reference' => $response->getTransactionResponse()->getTransId(),
            'amount' => $vars['amount'],
            'auth_code' => $response->getTransactionResponse()->getAuthCode(),
            'code' => $response->getTransactionResponse()->getMessages()[0]->getCode(),
            'description' => $response->getTransactionResponse()->getMessages()[0]->getDescription(),
            'invoices' => $vars['hashed_ids'],
        ];
    }

    private function transformPaymentTypeToConstant($type)
    {
        switch ($type) {
            case 'visa':
                return PaymentType::VISA;
                break;
            case 'mastercard':
                return PaymentType::MASTERCARD;
                break;
            default:
                return PaymentType::CREDIT_CARD_OTHER;
                break;
        }
    }

}

    // const CREDIT = 1;
    // const ACH = 4;
    // const VISA = 5;
    // const MASTERCARD = 6;
    // const AMERICAN_EXPRESS = 7;
    // const DISCOVER = 8;
    // const DINERS = 9;
    // const EUROCARD = 10;
    // const NOVA = 11;
    // const CREDIT_CARD_OTHER = 12;
    // const PAYPAL = 13;
    // const CARTE_BLANCHE = 16;
    // const UNIONPAY = 17;
    // const JCB = 18;
    // const LASER = 19;
    // const MAESTRO = 20;
    // const SOLO = 21;
    // const SWITCH = 22;
    // const ALIPAY = 27;
    // const SOFORT = 28;
    // const SEPA = 29;
    // const GOCARDLESS = 30;
    // const CRYPTO = 31;

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
// 
// 
// [2020-07-14 23:06:47] local.INFO: Stripe\PaymentIntent Object
// (
//     [id] => pi_1H4xD0Kmol8YQE9DKhrvV6Nc
//     [object] => payment_intent
//     [allowed_source_types] => Array
//         (
//             [0] => card
//         )

//     [amount] => 1000
//     [amount_capturable] => 0
//     [amount_received] => 1000
//     [application] => 
//     [application_fee_amount] => 
//     [canceled_at] => 
//     [cancellation_reason] => 
//     [capture_method] => automatic
//     [charges] => Stripe\Collection Object
//         (
//             [object] => list
//             [data] => Array
//                 (
//                     [0] => Stripe\Charge Object
//                         (
//                             [id] => ch_1H4xD0Kmol8YQE9Ds9b1ZWjw
//                             [object] => charge
//                             [amount] => 1000
//                             [amount_refunded] => 0
//                             [application] => 
//                             [application_fee] => 
//                             [application_fee_amount] => 
//                             [balance_transaction] => txn_1H4xD1Kmol8YQE9DE9qFoO0R
//                             [billing_details] => Stripe\StripeObject Object
//                                 (
//                                     [address] => Stripe\StripeObject Object
//                                         (
//                                             [city] => 
//                                             [country] => 
//                                             [line1] => 
//                                             [line2] => 
//                                             [postal_code] => 42334
//                                             [state] => 
//                                         )

//                                     [email] => 
//                                     [name] => sds
//                                     [phone] => 
//                                 )

//                             [calculated_statement_descriptor] => NODDY
//                             [captured] => 1
//                             [created] => 1594768006
//                             [currency] => usd
//                             [customer] => cus_He4VEiYldHJWqG
//                             [description] => Invoice 0023 for 10 for client Corwin Group
//                             [destination] => 
//                             [dispute] => 
//                             [disputed] => 
//                             [failure_code] => 
//                             [failure_message] => 
//                             [fraud_details] => Array
//                                 (
//                                 )

//                             [invoice] => 
//                             [livemode] => 
//                             [metadata] => Stripe\StripeObject Object
//                                 (
//                                 )

//                             [on_behalf_of] => 
//                             [order] => 
//                             [outcome] => Stripe\StripeObject Object
//                                 (
//                                     [network_status] => approved_by_network
//                                     [reason] => 
//                                     [risk_level] => normal
//                                     [risk_score] => 13
//                                     [seller_message] => Payment complete.
//                                     [type] => authorized
//                                 )

//                             [paid] => 1
//                             [payment_intent] => pi_1H4xD0Kmol8YQE9DKhrvV6Nc
//                             [payment_method] => pm_1H4mNAKmol8YQE9DUMRsuTXs
//                             [payment_method_details] => Stripe\StripeObject Object
//                                 (
//                                     [card] => Stripe\StripeObject Object
//                                         (
//                                             [brand] => visa
//                                             [checks] => Stripe\StripeObject Object
//                                                 (
//                                                     [address_line1_check] => 
//                                                     [address_postal_code_check] => pass
//                                                     [cvc_check] => 
//                                                 )

//                                             [country] => US
//                                             [exp_month] => 4
//                                             [exp_year] => 2024
//                                             [fingerprint] => oCjEXlb4syFKwgbJ
//                                             [funding] => credit
//                                             [installments] => 
//                                             [last4] => 4242
//                                             [network] => visa
//                                             [three_d_secure] => 
//                                             [wallet] => 
//                                         )

//                                     [type] => card
//                                 )

//                             [receipt_email] => 
//                             [receipt_number] => 
//                             [receipt_url] => https://pay.stripe.com/receipts/acct_19DXXPKmol8YQE9D/ch_1H4xD0Kmol8YQE9Ds9b1ZWjw/rcpt_HeFiiwzRZtnOpvHyohNN5JXtCYe8Rdc
//                             [refunded] => 
//                             [refunds] => Stripe\Collection Object
//                                 (
//                                     [object] => list
//                                     [data] => Array
//                                         (
//                                         )

//                                     [has_more] => 
//                                     [total_count] => 0
//                                     [url] => /v1/charges/ch_1H4xD0Kmol8YQE9Ds9b1ZWjw/refunds
//                                 )

//                             [review] => 
//                             [shipping] => 
//                             [source] => 
//                             [source_transfer] => 
//                             [statement_descriptor] => 
//                             [statement_descriptor_suffix] => 
//                             [status] => succeeded
//                             [transfer_data] => 
//                             [transfer_group] => 
//                         )

//                 )

//             [has_more] => 
//             [total_count] => 1
//             [url] => /v1/charges?payment_intent=pi_1H4xD0Kmol8YQE9DKhrvV6Nc
//         )

//     [client_secret] => pi_1H4xD0Kmol8YQE9DKhrvV6Nc_secret_TyE8n3Y3oaMqgqQvXvtKDOnYT
//     [confirmation_method] => automatic
//     [created] => 1594768006
//     [currency] => usd
//     [customer] => cus_He4VEiYldHJWqG
//     [description] => Invoice 0023 for 10 for client Corwin Group
//     [invoice] => 
//     [last_payment_error] => 
//     [livemode] => 
//     [metadata] => Stripe\StripeObject Object
//         (
//         )

//     [next_action] => 
//     [next_source_action] => 
//     [on_behalf_of] => 
//     [payment_method] => pm_1H4mNAKmol8YQE9DUMRsuTXs
//     [payment_method_options] => Stripe\StripeObject Object
//         (
//             [card] => Stripe\StripeObject Object
//                 (
//                     [installments] => 
//                     [network] => 
//                     [request_three_d_secure] => automatic
//                 )

//         )

//     [payment_method_types] => Array
//         (
//             [0] => card
//         )

//     [receipt_email] => 
//     [review] => 
//     [setup_future_usage] => 
//     [shipping] => 
//     [source] => 
//     [statement_descriptor] => 
//     [statement_descriptor_suffix] => 
//     [status] => succeeded
//     [transfer_data] => 
//     [transfer_group] => 
// )