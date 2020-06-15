<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
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
 * Class ChargePaymentProfile
 * @package App\PaymentDrivers\Authorize
 *
 */
class ChargePaymentProfile
{

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }


  function chargeCustomerProfile($profile_id, $payment_profile_id, $amount)
  {

    $this->authorize->init();
    
    // Set the transaction's refId
    $refId = 'ref' . time();

    $profileToCharge = new CustomerProfilePaymentType();
    $profileToCharge->setCustomerProfileId($profile_id);
    $paymentProfile = new PaymentProfileType();
    $paymentProfile->setPaymentProfileId($payment_profile_id);
    $profileToCharge->setPaymentProfile($paymentProfile);

    $transactionRequestType = new TransactionRequestType();
    $transactionRequestType->setTransactionType("authCaptureTransaction"); 
    $transactionRequestType->setAmount($amount);
    $transactionRequestType->setProfile($profileToCharge);

    $request = new CreateTransactionRequest();
    $request->setMerchantAuthentication($this->authorize->merchant_authentication);
    $request->setRefId( $refId);
    $request->setTransactionRequest( $transactionRequestType);
    $controller = new CreateTransactionController($request);
    $response = $controller->executeWithApiResponse($this->authorize->mode());


      if($response != null &&$response->getMessages()->getResultCode() == "Ok")
      {
        $tresponse = $response->getTransactionResponse();
        
	      if ($tresponse != null && $tresponse->getMessages() != null)   
        {
          echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
          echo  "Charge Customer Profile APPROVED  :" . "\n";
          echo " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
          echo " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
          echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n"; 
	        echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
        }
        else
        {
          echo "Transaction Failed \n";
          if($tresponse->getErrors() != null)
          {
            echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
            echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";            
          }
        }
      }
      else
      {
        echo "Transaction Failed \n";
        $tresponse = $response->getTransactionResponse();
        if($tresponse != null && $tresponse->getErrors() != null)
        {
          echo " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
          echo " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";                      
        }
        else
        {
          echo " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
          echo " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
        }
      }
    }

  }