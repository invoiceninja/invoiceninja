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
use App\Jobs\Mail\PaymentFailedMailer;
use Illuminate\Queue\SerializesModels;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Queue\InteractsWithQueue;
use App\PaymentDrivers\SquarePaymentDriver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SquareWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public CompanyGateway $company_gateway;

    public SquarePaymentDriver $driver;

    public \Square\SquareClient $square;

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

        $this->company_gateway = CompanyGateway::query()->withTrashed()->find($this->company_gateway_id);
        $this->driver = $this->company_gateway->driver()->init();
        $this->square = $this->driver->square;

        $status = $this->webhook_array['data']['object']['payment']['status'] ?? false;
        $payment_id = $this->webhook_array['data']['object']['payment']['id'] ?? null;

        $payment_status = false;

        match($status){
            'APPROVED' => $payment_status = false,
            'COMPLETED' => $payment_status = Payment::STATUS_COMPLETED,
            'PENDING' => $payment_status = Payment::STATUS_PENDING,
            'CANCELED' => $payment_status = Payment::STATUS_CANCELLED,
            'FAILED' => $payment_status = Payment::STATUS_FAILED,
            default => $payment_status = false,
        };

        if(!$payment_status){
            nlog("Square Webhook - Payment Status Not Found or not worthy of processing");
            nlog($this->webhook_array);
        }

        $payment = $this->retrieveOrCreatePayment($payment_id, $payment_status);

        /** If the status was pending and now is reporting as Failed / Cancelled - process failure path */
        if($payment->status_id == Payment::STATUS_PENDING && in_array($payment_status, [Payment::STATUS_CANCELLED, Payment::STATUS_FAILED])){
            $payment->service()->deletePayment();

            if ($this->driver->payment_hash) {
                $error = ctrans('texts.client_payment_failure_body', [
                    'invoice' => implode(',', $payment->invoices->pluck('number')->toArray()),
                    'amount' => array_sum(array_column($this->driver->payment_hash->invoices(), 'amount')) + $this->driver->payment_hash->fee_total, 
                ]);
            } else {
                $error = 'Payment for '.$payment->client->present()->name()." for {$payment->amount} failed";
            }

            PaymentFailedMailer::dispatch(
                $this->driver->payment_hash,
                $this->driver->client->company,
                $this->driver->client,
                $error
            );

        }
        elseif($payment->status_id == Payment::STATUS_PENDING && in_array($payment_status, [Payment::STATUS_COMPLETED, Payment::STATUS_COMPLETED])){
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->save();
        }
            
        //toggle pending to completed.
    }

    private function retrieveOrCreatePayment(?string $payment_reference, int $payment_status): ?\App\Models\Payment
    {

        $payment = Payment::withTrashed()->where('transaction_reference', $payment_reference)->first();

        if($payment) {
            nlog("payment found, returning");
            return $payment;
        }

        /** Handles the edge case where for some reason the payment has not yet been recorded in Invoice Ninja */
        $apiResponse = $this->square->getPaymentsApi()->getPayment($payment_reference);

        nlog("searching square for payment");

        if($apiResponse->isSuccess()){

            nlog("Searching by payment hash");

            $payment_hash_id = $apiResponse->getPayment()->getReferenceId() ?? false;
            $square_payment = $apiResponse->getPayment()->jsonSerialize();
            $payment_hash = PaymentHash::query()->where('hash', $payment_hash_id)->firstOrFail();

            $payment_hash->data = array_merge((array) $payment_hash->data, (array)$square_payment);
            $payment_hash->save();

            $this->driver->setPaymentHash($payment_hash);
            $this->driver->setClient($payment_hash->fee_invoice->client);

            $data = [
                'payment_type' => $this->source_type[$square_payment->source_type],
                'amount' => $payment_hash->amount_with_fee,
                'transaction_reference' => $square_payment->id,
                'gateway_type_id' => GatewayType::BANK_TRANSFER,
            ];

            $payment = $this->driver->createPayment($data, $payment_status);
            
            nlog("Creating payment");

            SystemLogger::dispatch(
                ['response' => $this->webhook_array, 'data' => $square_payment],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_SQUARE,
                $this->driver->client,
                $this->driver->client->company,
            );

            return $payment;

        }
        else{
            nlog("Square Webhook - Payment not found: {$payment_reference}");
            nlog($apiResponse->getErrors());
            return null;
        }
    }
}