<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Eway;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\PaymentDrivers\EwayPaymentDriver;
use App\Utils\Traits\MakesHash;

class Token
{
    use MakesHash;

    public $eway_driver;

    public function __construct(EwayPaymentDriver $eway_driver)
    {
        $this->eway_driver = $eway_driver;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $transaction = [
            'Customer' => [
                'TokenCustomerID' => $cgt->token,
            ],
            'Payment' => [
                'TotalAmount' => $this->eway_driver->convertAmount($amount),
            ],
            'TransactionType' => \Eway\Rapid\Enum\TransactionType::RECURRING,
        ];

        $response = $this->eway_driver->init()->eway->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

        $response_status = ErrorCode::getStatus($response->ResponseMessage);

        if (!$response_status['success']) {
            return $this->processUnsuccessfulPayment($response);
        }

        $payment = $this->processSuccessfulPayment($response);

        return $payment;
    }


    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->eway_driver->payment_hash->invoices(), 'amount')) + $this->eway_driver->payment_hash->fee_total;

        $data = [
            'gateway_type_id' => $cgt->gateway_type_id,
            'payment_type' => GatewayType::CREDIT_CARD_OTHER,
            'transaction_reference' => $response->Customer->Reference,
            'amount' => $amount,
        ];

        $payment = $this->eway_driver->createPayment($data);
        $payment->meta = $cgt->meta;
        $payment->save();

        $payment_hash->payment_id = $payment->id;
        $payment_hash->save();

        return $payment;
    }

    private function processUnsuccessfulPayment($response)
    {
        $response_status = ErrorCode::getStatus($response->ResponseMessage);

        $error = $response_status['message'];
        $error_code = $response->ResponseMessage;
        
        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $error_code,
        ];

        return $this->driver_class->processUnsuccessfulTransaction($data);
    }
}
