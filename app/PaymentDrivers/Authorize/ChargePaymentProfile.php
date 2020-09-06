<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Authorize;

use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CustomerProfilePaymentType;
use net\authorize\api\contract\v1\PaymentProfileType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\controller\CreateTransactionController;

/**
 * Class ChargePaymentProfile.
 */
class ChargePaymentProfile
{
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

        $transactionRequestType = new TransactionRequestType();
        $transactionRequestType->setTransactionType('authCaptureTransaction');
        $transactionRequestType->setAmount($amount);
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
                info(' Transaction Response code : '.$tresponse->getResponseCode());
                info('Charge Customer Profile APPROVED  :');
                info(' Charge Customer Profile AUTH CODE : '.$tresponse->getAuthCode());
                info(' Charge Customer Profile TRANS ID  : '.$tresponse->getTransId());
                info(' Code : '.$tresponse->getMessages()[0]->getCode());
                info(' Description : '.$tresponse->getMessages()[0]->getDescription());
                //info(" Charge Customer Profile TRANS STATUS  : " . $tresponse->getTransactionStatus() );
                //info(" Charge Customer Profile Amount : " . $tresponse->getAuthAmount());

                info(' Code : '.$tresponse->getMessages()[0]->getCode());
                info(' Description : '.$tresponse->getMessages()[0]->getDescription());
                info(print_r($tresponse->getMessages()[0], 1));
            } else {
                info('Transaction Failed ');
                if ($tresponse->getErrors() != null) {
                    info(' Error code  : '.$tresponse->getErrors()[0]->getErrorCode());
                    info(' Error message : '.$tresponse->getErrors()[0]->getErrorText());
                    info(print_r($tresponse->getErrors()[0], 1));
                }
            }
        } else {
            info('Transaction Failed ');
            $tresponse = $response->getTransactionResponse();
            if ($tresponse != null && $tresponse->getErrors() != null) {
                info(' Error code  : '.$tresponse->getErrors()[0]->getErrorCode());
                info(' Error message : '.$tresponse->getErrors()[0]->getErrorText());
                info(print_r($tresponse->getErrors()[0], 1));
            } else {
                info(' Error code  : '.$response->getMessages()->getMessage()[0]->getCode());
                info(' Error message : '.$response->getMessages()->getMessage()[0]->getText());
            }
        }

        return [
          'response'           => $response,
          'amount'             => $amount,
          'profile_id'         => $profile_id,
          'payment_profile_id' => $payment_profile_id,
        ];
    }
}
