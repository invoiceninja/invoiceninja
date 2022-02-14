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

namespace App\PaymentDrivers\Authorize;

use App\Exceptions\GenericPaymentDriverFailure;
use App\Models\Client;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\CreateCustomerProfileRequest;
use net\authorize\api\contract\v1\CustomerProfileType;
use net\authorize\api\contract\v1\GetCustomerProfileIdsRequest;
use net\authorize\api\contract\v1\GetCustomerProfileRequest;
use net\authorize\api\controller\CreateCustomerProfileController;
use net\authorize\api\controller\GetCustomerProfileController;
use net\authorize\api\controller\GetCustomerProfileIdsController;

/**
 * Class AuthorizeCustomer.
 */
class AuthorizeCustomer
{
    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    private function getCustomerProfileIds()
    {

        // Get all existing customer profile ID's
        $request = new GetCustomerProfileIdsRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $controller = new GetCustomerProfileIdsController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());
        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
        {

            return $response->getIds();

            // echo "GetCustomerProfileId's SUCCESS: " . "\n";
            // $profileIds[] = $response->getIds();
            // echo "There are " . count($profileIds[0]) . " Customer Profile ID's for this Merchant Name and Transaction Key" . "\n";
        }
        else
        {
            return [];

            nlog( "GetCustomerProfileId's ERROR :  Invalid response");
            $errorMessages = $response->getMessages()->getMessage();
            nlog( "Response : " . $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
        }

    }

    private function getCustomerProfile($customer_profile_id)
    {

      $request = new GetCustomerProfileRequest();
      $request->setMerchantAuthentication($this->authorize->merchant_authentication);
      $request->setCustomerProfileId($customer_profile_id);
      $controller = new GetCustomerProfileController($request);
      $response = $controller->executeWithApiResponse($this->authorize->mode());
      if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
      {
        $profileSelected = $response->getProfile();
        $paymentProfilesSelected = $profileSelected->getPaymentProfiles();

        return [
            'profile' => $profileSelected,
            'payment_profiles' => $paymentProfilesSelected,
            'error' => ''
        ];

      }
      else
      {

        nlog("ERROR :  GetCustomerProfile: Invalid response");
        $errorMessages = $response->getMessages()->getMessage();
        nlog("Response : " . $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());

        return [
            'profile' => NULL,
            'payment_profiles' => NULL,
            'error' => $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText(),
        ];

      }
    }

}    


