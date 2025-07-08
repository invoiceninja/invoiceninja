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

namespace App\PaymentDrivers\Authorize;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\CreditCardType;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\HeldTransactionRequestType;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\contract\v1\UpdateHeldTransactionRequest;
use net\authorize\api\controller\UpdateHeldTransactionController;

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

        $transaction = $transaction_details->getTransaction();
        $payment_details = $transaction->getPayment();

        $transaction_status = $transaction->getTransactionStatus();

        $transaction_type = match($transaction_status){
            'capturedPendingSettlement' => 'voidTransaction',
            'refundPendingSettlement' => 'refundTransaction',
            'FDSAuthorizedPendingReview' => 'voidHeldTransaction',
            default => 'refundTransaction',
        };

        if ($transaction_type == 'voidTransaction') {
            $amount = $transaction->getAuthAmount();
        }
        elseif ($transaction_type == 'voidHeldTransaction') {
            $amount = $transaction->getAuthAmount();
            return $this->declineHeldTransaction($payment, $amount);
        }

        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        //create a transaction
        $transactionRequest = new TransactionRequestType();
        $transactionRequest->setTransactionType($transaction_type);
        $transactionRequest->setAmount($amount);
        $transactionRequest->setRefTransId($payment->transaction_reference);

        // Set payment info based on type
        if ($payment_details->getCreditCard()) {
            $creditCard = new CreditCardType();
            $creditCard->setCardNumber($payment_details->getCreditCard()->getCardNumber());
            $creditCard->setExpirationDate($payment_details->getCreditCard()->getExpirationDate());
            $paymentOne = new PaymentType();
            $paymentOne->setCreditCard($creditCard);
            $transactionRequest->setPayment($paymentOne);
        } elseif ($payment_details->getBankAccount()) {
            $bankAccount = new \net\authorize\api\contract\v1\BankAccountType();
            $bankAccount->setRoutingNumber($payment_details->getBankAccount()->getRoutingNumber());
            $bankAccount->setAccountNumber($payment_details->getBankAccount()->getAccountNumber());
            $bankAccount->setAccountType($payment_details->getBankAccount()->getAccountType());
            $bankAccount->setNameOnAccount($payment_details->getBankAccount()->getNameOnAccount());
            $bankAccount->setBankName($payment_details->getBankAccount()->getBankName());
            $bankAccount->setEcheckType('WEB');
            $paymentOne = new PaymentType();
            $paymentOne->setBankAccount($bankAccount);
            $transactionRequest->setPayment($paymentOne);
        }

        $solution = new \net\authorize\api\contract\v1\SolutionType();
        $solution->setId($this->authorize->company_gateway->getConfigField('testMode') ? 'AAA100303' : 'AAA172036');
        $transactionRequest->setSolution($solution);

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
                        'voided' => $transaction_status == 'capturedPendingSettlement' ? true : false,
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


    public function declineHeldTransaction(Payment $payment, $amount)
    {
            
        $this->authorize->init();
        $refId = 'ref' . time();

        //create a transaction
        $transactionRequestType = new HeldTransactionRequestType();
        $transactionRequestType->setAction("decline"); //other possible value: decline
        $transactionRequestType->setRefTransId($payment->transaction_reference);

        $request = new UpdateHeldTransactionRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setHeldTransactionRequest($transactionRequestType);

        $controller = new UpdateHeldTransactionController($request);
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
                        'voided' => true,
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
