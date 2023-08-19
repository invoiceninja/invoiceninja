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

namespace App\PaymentDrivers\Square;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Bus\Queueable;
use App\Models\CompanyGateway;
use App\Jobs\Util\SystemLogger;
use Illuminate\Queue\SerializesModels;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SquareWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public CompanyGateway $company_gateway;

    private array $source_type = [
        'CARD' => PaymentType::CREDIT_CARD_OTHER, 
        'BANK_ACCOUNT' => PaymentType::ACH, 
        'WALLET' => PaymentType::CREDIT_CARD_OTHER, 
        'BUY_NOW_PAY_LATER' => PaymentType::CREDIT_CARD_OTHER, 
        'SQUARE_ACCOUNT' => PaymentType::CREDIT_CARD_OTHER, 
        'CASH' => PaymentType::CASH, 
        'EXTERNAL' =>PaymentType::CREDIT_CARD_OTHER 
    ];

    public function __construct(public array $webhook_array, public string $company_key, public int $company_gateway_id)
    {
    }


/**
  * {
  * "merchant_id": "6SSW7HV8K2ST5",
  * "type": "payment.created",
  * "event_id": "13b867cf-db3d-4b1c-90b6-2f32a9d78124",
  * "created_at": "2020-02-06T21:27:30.792Z",
  * "data": {
    * "type": "payment",
    * "id": "KkAkhdMsgzn59SM8A89WgKwekxLZY",
    * "object": {
      * "payment": {
        * "id": "hYy9pRFVxpDsO1FB05SunFWUe9JZY",
        * "created_at": "2020-11-22T21:16:51.086Z",
        * "updated_at": "2020-11-22T21:16:51.198Z",
        * "amount_money": {
          * "amount": 100,
          * "currency": "USD"
        * },
        * "status": "APPROVED",
        * "delay_duration": "PT168H",
        * "source_type": "CARD",
        * "card_details": {
          * "status": "AUTHORIZED",
          * "card": {
            * "card_brand": "MASTERCARD",
            * "last_4": "9029",
            * "exp_month": 11,
            * "exp_year": 2022,
            * "fingerprint": "sq-1-Tvruf3vPQxlvI6n0IcKYfBukrcv6IqWr8UyBdViWXU2yzGn5VMJvrsHMKpINMhPmVg",
            * "card_type": "CREDIT",
            * "prepaid_type": "NOT_PREPAID",
            * "bin": "540988"
          * },
          * "entry_method": "KEYED",
          * "cvv_status": "CVV_ACCEPTED",
          * "avs_status": "AVS_ACCEPTED",
          * "statement_description": "SQ *DEFAULT TEST ACCOUNT",
          * "card_payment_timeline": {
          * "authorized_at": "2020-11-22T21:16:51.198Z"
          *     
        */
    public function handle()
    {
        nlog("Square Webhook");

        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $this->company_gateway = CompanyGateway::withTrashed()->find($this->company_gateway_id);

        $status = $this->webhook_array['data']['object']['payment']['status'] ?? false;
        $payment_id = $this->webhook_array['data']['object']['payment']['id'] ?? null;

        $payment = $this->retrieveOrCreatePayment($payment_id);

        // APPROVED, PENDING, COMPLETED, CANCELED, or FAILED
        if(in_array($status, ['APPROVED', 'COMPLETED'])){

        }
        elseif(in_array($status, ['PENDING'])){

        }
        elseif(in_array($status, ['CANCELED', 'FAILED'])){

        }
        else{
            nlog("Square Webhook status not handled: $status");
        }

        // if(!isset($this->webhook_array['type']))
        //     nlog("Checkout Webhook type not set");

        // match($this->webhook_array['type']){
        //     'payment_approved' => $this->paymentApproved(),
        // };

    }

    private function retrieveOrCreatePayment(?string $payment_reference): \App\Models\Payment
    {

        $payment = Payment::withTrashed()->where('transaction_reference', $payment_reference)->first();

        if($payment)
            return $payment;

        $square = $this->company_gateway->driver()->init();
        $apiResponse = $square->getPaymentsApi()->getPayment($payment_reference);

// {
//   "payment": {
//     "id": "bP9mAsEMYPUGjjGNaNO5ZDVyLhSZY",
//     "created_at": "2021-10-13T19:34:33.524Z",
//     "updated_at": "2021-10-13T19:34:34.339Z",
//     "amount_money": {
//       "amount": 555,
//       "currency": "USD"
//     },
//     "status": "COMPLETED",
//     "delay_duration": "PT168H",
//     "source_type": "CARD",
//     "card_details": {
//       "status": "CAPTURED",
//       "card": {
//         "card_brand": "VISA",
//         "last_4": "1111",
//         "exp_month": 11,
//         "exp_year": 2022,
//         "fingerprint": "sq-1-Hxim77tbdcbGejOejnoAklBVJed2YFLTmirfl8Q5XZzObTc8qY_U8RkwzoNL8dCEcQ",
//         "card_type": "DEBIT",
//         "prepaid_type": "NOT_PREPAID",
//         "bin": "411111"
//       },
//       "entry_method": "KEYED",
//       "cvv_status": "CVV_ACCEPTED",
//       "avs_status": "AVS_ACCEPTED",
//       "auth_result_code": "2Nkw7q",
//       "statement_description": "SQ *EXAMPLE TEST GOSQ.C",
//       "card_payment_timeline": {
//         "authorized_at": "2021-10-13T19:34:33.680Z",
//         "captured_at": "2021-10-13T19:34:34.340Z"
//       }
//     },
//     "location_id": "L88917AVBK2S5",
//     "order_id": "d7eKah653Z579f3gVtjlxpSlmUcZY",
//     "processing_fee": [
//       {
//         "effective_at": "2021-10-13T21:34:35.000Z",
//         "type": "INITIAL",
//         "amount_money": {
//           "amount": 34,
//           "currency": "USD"
//         }
//       }
//     ],
//     "note": "Test Note",
//     "total_money": {
//       "amount": 555,
//       "currency": "USD"
//     },
//     "approved_money": {
//       "amount": 555,
//       "currency": "USD"
//     },
//     "employee_id": "TMoK_ogh6rH1o4dV",
//     "receipt_number": "bP9m",
//     "receipt_url": "https://squareup.com/receipt/preview/bP9mAsEMYPUGjjGNaNO5ZDVyLhSZY",
//     "delay_action": "CANCEL",
//     "delayed_until": "2021-10-20T19:34:33.524Z",
//     "team_member_id": "TMoK_ogh6rH1o4dV",
//     "application_details": {
//       "square_product": "VIRTUAL_TERMINAL",
//       "application_id": "sq0ids-Pw67AZAlLVB7hsRmwlJPuA"
//     },
//     "version_token": "56pRkL3slrzet2iQrTp9n0bdJVYTB9YEWdTNjQfZOPV6o"
//   }
// }

        if($apiResponse->isSuccess()){

            $payment_hash_id = $apiResponse->getPayment()->getReferenceId() ?? false;
            $square_payment = $apiResponse->getPayment()->jsonSerialize();
            $payment_hash = PaymentHash::where('hash',$payment_hash_id)->first();

            $payment_hash->data = array_merge((array) $payment_hash->data, (array)$square_payment);
            $payment_hash->save();

            $square->setPaymentHash($payment_hash);
            $square->setClient($payment_hash->fee_invoice->client);

            $data = [
                'payment_type' => $this->source_type[$square_payment->source_type],
                'amount' => $payment_hash->amount_with_fee,
                'transaction_reference' => $square_payment->id,
                'gateway_type_id' => GatewayType::BANK_TRANSFER,
            ];

            $payment = $square->createPayment($data, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $this->webhook_array, 'data' => $square_payment],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_SQUARE,
                $square->client,
                $square->client->company,
            );

            return $payment;

        }
        else{
            nlog("Square Webhook Payment not found: $payment_reference");
        }
    }
}