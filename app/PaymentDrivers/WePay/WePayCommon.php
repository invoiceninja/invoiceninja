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

namespace App\PaymentDrivers\WePay;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\SystemLog;

trait WePayCommon
{
    private function processSuccessfulPayment($response, $payment_status, $gateway_type, $return_payment = false)
    {
        if ($gateway_type == GatewayType::BANK_TRANSFER) {
            $payment_type = PaymentType::ACH;
        } else {
            $payment_type = PaymentType::CREDIT_CARD_OTHER;
        }

        $data = [
            'payment_type' => $payment_type,
            'amount' => $response->amount,
            'transaction_reference' => $response->checkout_id,
            'gateway_type_id' => $gateway_type,
        ];

        $payment = $this->wepay_payment_driver->createPayment($data, $payment_status);

        SystemLogger::dispatch(
            ['response' => $this->wepay_payment_driver->payment_hash->data->server_response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_WEPAY,
            $this->wepay_payment_driver->client,
            $this->wepay_payment_driver->client->company,
        );

        if ($return_payment) {
            return $payment;
        }

        return redirect()->route('client.payments.show', ['payment' => $this->wepay_payment_driver->encodePrimaryKey($payment->id)]);
    }

    private function processUnSuccessfulPayment($response, $payment_status)
    {
        $this->wepay_payment_driver->sendFailureMail($response->state);

        $message = [
            'server_response' => $response,
            'data' => $this->wepay_payment_driver->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_WEPAY,
            $this->wepay_payment_driver->client,
            $this->wepay_payment_driver->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }
}
