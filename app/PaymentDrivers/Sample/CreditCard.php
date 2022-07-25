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

namespace App\PaymentDrivers\Sample;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreditCard
{
    use MakesHash;

    public $driver_class;

    public function __construct(PaymentDriver $driver_class)
    {
        $this->driver_class = $driver_class;
    }

    public function authorizeView($data)
    {
    }

    public function authorizeRequest($request)
    {
    }

    public function paymentView($data)
    {
    }

    public function processPaymentResponse($request)
    {
    }

    /* This method is stubbed ready to go - you just need to harvest the equivalent 'transaction_reference' */
    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->driver_class->payment_hash->invoices(), 'amount')) + $this->driver_class->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        // $payment_record['transaction_reference'] = $response->transaction_id;

        $payment = $this->driver_class->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        /*Harvest your own errors here*/
        // $error = $response->status_message;

        // if(property_exists($response, 'approval_message') && $response->approval_message)
        //     $error .= " - {$response->approval_message}";

        // $error_code = property_exists($response, 'approval_message') ? $response->approval_message : 'Undefined code';

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $error_code,
        ];

        return $this->driver_class->processUnsuccessfulTransaction($data);
    }

    /* Helpers */

    /*
      You will need some helpers to handle successful and unsuccessful responses

      Some considerations after a succesful transaction include:

      Logging of events: success +/- failure
      Recording a payment
      Notifications
     */
}
