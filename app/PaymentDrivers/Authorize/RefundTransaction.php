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
 * Class RefundTransaction
 * @package App\PaymentDrivers\Authorize
 *
 */
class RefundTransaction
{

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

	function refundTransaction($transaction_reference, $amount, $payment_profile_id, $profile_id)
	{

	   	$this->authorize->init();
	    
	    // Set the transaction's refId
	    $refId = 'ref' . time();

	    $paymentProfile = new PaymentProfileType();
	    $paymentProfile->setPaymentProfileId( $payment_profile_id );

	    // set customer profile
	    $customerProfile = new CustomerProfilePaymentType();
	    $customerProfile->setCustomerProfileId( $profile_id );
	    $customerProfile->setPaymentProfile( $paymentProfile );

	    //create a transaction
	    $transactionRequest = new TransactionRequestType();
	    $transactionRequest->setTransactionType("refundTransaction"); 
	    $transactionRequest->setAmount($amount);
    	$transactionRequest->setProfile($customerProfile);
	    $transactionRequest->setRefTransId($transaction_reference);

	    $request = new CreateTransactionRequest();
	    $request->setMerchantAuthentication($this->authorize->merchant_authentication);
	    $request->setRefId($refId);
	    $request->setTransactionRequest($transactionRequest);
	    $controller = new CreateTransactionController($request);
	    $response = $controller->executeWithApiResponse($this->authorize->mode());

	    if ($response != null)
	    {
	      if($response->getMessages()->getResultCode() == "Ok")
	      {
	        $tresponse = $response->getTransactionResponse();
	        
		      if ($tresponse != null && $tresponse->getMessages() != null)   
	        {
	          echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
	          echo "Refund SUCCESS: " . $tresponse->getTransId() . "\n";
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
	    else
	    {
	      echo  "No response returned \n";
	    }

	    return $response;
	  }


}