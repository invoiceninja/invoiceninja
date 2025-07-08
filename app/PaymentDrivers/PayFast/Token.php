<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\PayFast;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\PayFastPaymentDriver;

class Token
{
    public $payfast;

    public function __construct(PayFastPaymentDriver $payfast)
    {
        $this->payfast = $payfast;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $amount = (int)round(($amount * pow(10, $this->payfast->client->currency()->precision)), 0);

        try {
            $payfast = new \PayFast\PayFastApi(
                [
                    'merchantId' => (string)$this->payfast->company_gateway->getConfigField('merchantId'),
                    'merchantKey' => $this->payfast->company_gateway->getConfigField('merchantKey'),
                    'passPhrase' => $this->payfast->company_gateway->getConfigField('passphrase'),
                    'testMode' => $this->payfast->company_gateway->getConfigField('testMode')
                ]
            );

            $data = [
                'amount' => $amount,
                'item_name' => ctrans('texts.invoices').': '.collect($payment_hash->invoices())->pluck('invoice_number'),
                'm_payment_id' => $payment_hash->hash,
            ];

            $response = $payfast->subscriptions->adhoc($cgt->token, $data);

            nlog("TokenBilling");
            nlog($response);
            nlog(now()->format('Y-m-d H:i:s'));

            if($response['code'] == 200 && $response['status'] == 'success') {
                return $this->processSuccessfulPayment($response);
            }

            return $this->processUnsuccessfulPayment($response, $payment_hash);

        } catch (Exception $e) {
            echo 'There was an exception: '.$e->getMessage();
            return $this->processUnsuccessfulPayment($e->getMessage());
        }

    }

// Array
// (
//     [code] => 200
//     [status] => success
//     [data] => Array
//         (
//             [response] => true
//             [message] => Transaction was successful (00)
//             [pf_payment_id] => 2577761
//         )

// )

    private function processSuccessfulPayment(array $response)
    {
        
        $payment_record = [];
        $payment_record['amount'] =  array_sum(array_column($this->payfast->payment_hash->invoices(), 'amount')) + $this->payfast->payment_hash->fee_total;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $response['data']['pf_payment_id'];
        $payment_record['idempotency_key'] = $response['data']['pf_payment_id'].$this->payfast->payment_hash->hash;
        $payment = $this->payfast->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return $payment;
    }

    private function processUnsuccessfulPayment($response)
    {
        $error_message = $response['data']['message'];
        $error_code = $response['code'];

        $this->payfast->sendFailureMail($error_message);

        $message = [
            'server_response' => $response,
            'data' => $this->payfast->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYFAST,
            $this->payfast->client,
            $this->payfast->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);

    }

}
