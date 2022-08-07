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

use App\Models\Invoice;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\Utils\Traits\MakesHash;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\ExtendedAmountType;
use net\authorize\api\contract\v1\OrderType;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\controller\CreateTransactionController;

/**
 * Class ChargePaymentProfile.
 */
class ChargePaymentProfile
{
    use MakesHash;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function chargeCustomerProfile($profile_id, $payment_profile_id, $amount)
    {
        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        $profileToCharge = new CustomerProfilePaymentType();
        $profileToCharge->setCustomerProfileId($profile_id);
        $paymentProfile = new PaymentProfileType();
        $paymentProfile->setPaymentProfileId($payment_profile_id);
        $profileToCharge->setPaymentProfile($paymentProfile);

        $invoice_numbers = '';
        $taxAmount = 0;
        $invoiceTotal = 0;
        $invoiceTaxes = 0;

        if ($this->authorize->payment_hash->data) {
            $invoice_numbers = collect($this->authorize->payment_hash->data->invoices)->pluck('invoice_number')->implode(',');
            $invObj = Invoice::whereIn('id', $this->transformKeys(array_column($this->authorize->payment_hash->invoices(), 'invoice_id')))->withTrashed()->get();

            $invoiceTotal = round($invObj->pluck('amount')->sum(), 2);
            $invoiceTaxes = round($invObj->pluck('total_taxes')->sum(), 2);

            if ($invoiceTotal != $amount) {
                $taxRatio = $amount / $invoiceTotal;
                $taxAmount = round($invoiceTaxes * $taxRatio, 2);
            } else {
                $taxAmount = $invoiceTaxes;
            }
        }

        $description = "Invoices: {$invoice_numbers} for {$amount} for client {$this->authorize->client->present()->name()}";

        $order = new OrderType();
        $order->setInvoiceNumber(substr($invoice_numbers, 0, 19));
        $order->setDescription(substr($description, 0, 255));

        $tax = new ExtendedAmountType();
        $tax->setName('tax');
        $tax->setAmount($taxAmount);

        $transactionRequestType = new TransactionRequestType();
        $transactionRequestType->setTransactionType('authCaptureTransaction');
        $transactionRequestType->setAmount($amount);
        $transactionRequestType->setTax($tax);
        $transactionRequestType->setTaxExempt(empty($taxAmount));
        $transactionRequestType->setOrder($order);
        $transactionRequestType->setProfile($profileToCharge);
        $transactionRequestType->setCurrencyCode($this->authorize->client->currency()->code);

        $request = new CreateTransactionRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setRefId($refId);
        $request->setTransactionRequest($transactionRequestType);
        $controller = new CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());

        if ($response != null && $response->getMessages()->getResultCode() == 'Ok') {
            $tresponse = $response->getTransactionResponse();

            if ($tresponse != null && $tresponse->getMessages() != null) {
                nlog(' Transaction Response code : '.$tresponse->getResponseCode());
                nlog('Charge Customer Profile APPROVED  :');
                nlog(' Charge Customer Profile AUTH CODE : '.$tresponse->getAuthCode());
                nlog(' Charge Customer Profile TRANS ID  : '.$tresponse->getTransId());
                nlog(' Code : '.$tresponse->getMessages()[0]->getCode());
                nlog(' Description : '.$tresponse->getMessages()[0]->getDescription());
                //nlog(" Charge Customer Profile TRANS STATUS  : " . $tresponse->getTransactionStatus() );
                //nlog(" Charge Customer Profile Amount : " . $tresponse->getAuthAmount());

                nlog(' Code : '.$tresponse->getMessages()[0]->getCode());
                nlog(' Description : '.$tresponse->getMessages()[0]->getDescription());
                nlog(print_r($tresponse->getMessages()[0], 1));
            } else {
                nlog('Transaction Failed ');
                if ($tresponse->getErrors() != null) {
                    nlog(' Error code  : '.$tresponse->getErrors()[0]->getErrorCode());
                    nlog(' Error message : '.$tresponse->getErrors()[0]->getErrorText());
                    nlog(print_r($tresponse->getErrors()[0], 1));
                }
            }
        } else {
            nlog('Transaction Failed ');
            $tresponse = $response->getTransactionResponse();
            if ($tresponse != null && $tresponse->getErrors() != null) {
                nlog(' Error code  : '.$tresponse->getErrors()[0]->getErrorCode());
                nlog(' Error message : '.$tresponse->getErrors()[0]->getErrorText());
                nlog(print_r($tresponse->getErrors()[0], 1));
            } else {
                nlog(' Error code  : '.$response->getMessages()->getMessage()[0]->getCode());
                nlog(' Error message : '.$response->getMessages()->getMessage()[0]->getText());
            }
        }

        return [
            'response'           => $tresponse,
            'amount'             => $amount,
            'profile_id'         => $profile_id,
            'payment_profile_id' => $payment_profile_id,
        ];
    }
}
