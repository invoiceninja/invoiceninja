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

use App\Exceptions\GenericPaymentDriverFailure;
use App\Models\Client;
use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\CreateCustomerProfileRequest;
use net\authorize\api\contract\v1\CustomerAddressType;
use net\authorize\api\contract\v1\CustomerPaymentProfileType;
use net\authorize\api\contract\v1\CustomerProfileType;
use net\authorize\api\controller\CreateCustomerProfileController;

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

        $contact = $this->client->primary_contact()->first();
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
}
