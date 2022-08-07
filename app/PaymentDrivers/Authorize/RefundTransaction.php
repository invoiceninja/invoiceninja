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

namespace App\PaymentDrivers\Authorize;

use App\Jobs\Util\SystemLogger;
use App\Models\Payment;
use App\Models\SystemLog;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\controller\CreateTransactionController;

/**
 * Class RefundTransaction.
 */
class RefundTransaction
{
    public $authorize;

    public $authorize_transaction;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
        $this->authorize_transaction = new AuthorizeTransactions($this->authorize);
    }

    public function refundTransaction(Payment $payment, $amount)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $transaction_details = $this->authorize_transaction->getTransactionDetails($payment->transaction_reference);

        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        $paymentProfile = new PaymentProfileType();
        $paymentProfile->setPaymentProfileId($transaction_details->getTransaction()->getProfile()->getCustomerPaymentProfileId());

        // set customer profile
        $customerProfile = new CustomerProfilePaymentType();
        $customerProfile->setCustomerProfileId($transaction_details->getTransaction()->getProfile()->getCustomerProfileId());
        $customerProfile->setPaymentProfile($paymentProfile);

        //create a transaction
        $transactionRequest = new TransactionRequestType();
        $transactionRequest->setTransactionType('refundTransaction');
        $transactionRequest->setAmount($amount);
        $transactionRequest->setProfile($customerProfile);
        $transactionRequest->setRefTransId($payment->transaction_reference);

        $request = new CreateTransactionRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequest);
        $controller = new CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());

        if ($response != null) {
            if ($response->getMessages()->getResultCode() == 'Ok') {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    $data = [
                        'transaction_reference' => $tresponse->getTransId(),
                        'success' => true,
                        'description' => $tresponse->getMessages()[0]->getDescription(),
                        'code' => $tresponse->getMessages()[0]->getCode(),
                        'transaction_response' => $tresponse->getResponseCode(),
                        'payment_id' => $payment->id,
                        'amount' => $amount,
                    ];

                    SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

                    return $data;
                } else {
                    if ($tresponse->getErrors() != null) {
                        $data = [
                            'transaction_reference' => '',
                            'transaction_response' => '',
                            'success' => false,
                            'description' => $tresponse->getErrors()[0]->getErrorText(),
                            'code' => $tresponse->getErrors()[0]->getErrorCode(),
                            'payment_id' => $payment->id,
                            'amount' => $amount,
                        ];

                        SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

                        return $data;
                    }
                }
            } else {
                echo "Transaction Failed \n";
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $data = [
                        'transaction_reference' => '',
                        'transaction_response' => '',
                        'success' => false,
                        'description' => $tresponse->getErrors()[0]->getErrorText(),
                        'code' => $tresponse->getErrors()[0]->getErrorCode(),
                        'payment_id' => $payment->id,
                        'amount' => $amount,
                    ];

                    SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

                    return $data;
                } else {
                    $data = [
                        'transaction_reference' => '',
                        'transaction_response' => '',
                        'success' => false,
                        'description' => $response->getMessages()->getMessage()[0]->getText(),
                        'code' => $response->getMessages()->getMessage()[0]->getCode(),
                        'payment_id' => $payment->id,
                        'amount' => $amount,
                    ];

                    SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

                    return $data;
                }
            }
        } else {
            $data = [
                'transaction_reference' => '',
                'transaction_response' => '',
                'success' => false,
                'description' => 'No response returned',
                'code' => 'No response returned',
                'payment_id' => $payment->id,
                'amount' => $amount,
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

            return $data;
        }

        $data = [
            'transaction_reference' => '',
            'transaction_response' => '',
            'success' => false,
            'description' => 'No response returned',
            'code' => 'No response returned',
            'payment_id' => $payment->id,
            'amount' => $amount,
        ];

        SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);
    }
}
