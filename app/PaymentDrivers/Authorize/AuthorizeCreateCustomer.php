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

use App\Exceptions\GenericPaymentDriverFailure;
use App\Models\Client;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\CreateCustomerProfileRequest;
use net\authorize\api\contract\v1\CustomerProfileType;
use net\authorize\api\contract\v1\GetCustomerProfileRequest;
use net\authorize\api\controller\CreateCustomerProfileController;
use net\authorize\api\controller\GetCustomerProfileController;

/**
 * Class BaseDriver.
 */
class AuthorizeCreateCustomer
{
    public $authorize;

    public $client;

    public function __construct(AuthorizePaymentDriver $authorize, Client $client)
    {
        $this->authorize = $authorize;

        $this->client = $client;
    }

    public function create($data = null)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->authorize->init();
        // Create the Bill To info for new payment type

        $contact = $this->client->primary_contact()->first() ?: $this->client->contacts()->first();
        $refId = 'ref'.time();

        // Create a new CustomerProfileType and add the payment profile object
        $customerProfile = new CustomerProfileType();
        $customerProfile->setDescription($this->client->present()->name());
        $customerProfile->setMerchantCustomerId('M_'.time());
        $customerProfile->setEmail($this->client->present()->email());

        // Assemble the complete transaction request
        $request = new CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setRefId($refId);
        $request->setProfile($customerProfile);

        // Create the controller and get the response
        $controller = new CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            return $response->getCustomerProfileId();
        } else {
            $errorMessages = $response->getMessages()->getMessage();

            $message = 'Unable to add customer to Authorize.net gateway';

            if (is_array($errorMessages)) {
                $message = $errorMessages[0]->getCode().'  '.$errorMessages[0]->getText();
            }

            throw new GenericPaymentDriverFailure($message);
        }
    }

    public function get($profileIdRequested)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->authorize->init();
        $request = new GetCustomerProfileRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setCustomerProfileId($profileIdRequested);

        $controller = new GetCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->authorize->merchant_authentication);

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            echo 'GetCustomerProfile SUCCESS : '."\n";
            $profileSelected = $response->getProfile();
            $paymentProfilesSelected = $profileSelected->getPaymentProfiles();
            echo 'Profile Has '.count($paymentProfilesSelected).' Payment Profiles'."\n";

            if ($response->getSubscriptionIds() != null) {
                if ($response->getSubscriptionIds() != null) {
                    echo 'List of subscriptions:';
                    foreach ($response->getSubscriptionIds() as $subscriptionid) {
                        echo $subscriptionid."\n";
                    }
                }
            }
        } else {
            echo "ERROR :  GetCustomerProfile: Invalid response\n";
            $errorMessages = $response->getMessages()->getMessage();
            echo 'Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText()."\n";
        }

        return $response;
    }

    // This is how we can harvest client profiles and attach them within Invoice Ninja
// $request = new net\authorize\api\contract\v1\GetCustomerProfileRequest();
// $request->setMerchantAuthentication($driver->merchant_authentication);
// $request->setCustomerProfileId($gateway_customer_reference);
// $controller = new net\authorize\api\controller\GetCustomerProfileController($request);
// $response = $controller->executeWithApiResponse($driver->mode());

// if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
// {
//   echo "GetCustomerProfile SUCCESS : " .  "\n";
//   $profileSelected = $response->getProfile();
//   $paymentProfilesSelected = $profileSelected->getPaymentProfiles();
//   echo "Profile Has " . count($paymentProfilesSelected). " Payment Profiles" . "\n";

// foreach ($profileSelected->getPaymentProfiles() as $paymentProfile) {
//   echo "\nCustomer Profile ID: " . $paymentProfile->getCustomerProfileId() . "\n";
//   echo "Payment profile ID: " . $paymentProfile->getCustomerPaymentProfileId() . "\n";
//   echo "Credit Card Number: " . $paymentProfile->getPayment()->getCreditCard()->getCardNumber() . "\n";
//   if ($paymentProfile->getBillTo() != null) {
//       echo "First Name in Billing Address: " . $paymentProfile->getBillTo()->getFirstName() . "\n";
//   }
// }
}
