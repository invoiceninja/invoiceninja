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

use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\GetTransactionDetailsRequest;
use net\authorize\api\controller\GetTransactionDetailsController;

/**
 * Class AuthorizeTransactions.
 */
class AuthorizeTransactions
{
    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function getTransactionDetails($transactionId)
    {
        /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        $request = new GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setTransId($transactionId);

        $controller = new GetTransactionDetailsController($request);

        $response = $controller->executeWithApiResponse($this->authorize->mode());

        // if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
        if ($response != null && $response->getMessages() != null) {
            nlog('SUCCESS: Transaction Status:'.$response->getTransaction()->getTransactionStatus());
            nlog('                Auth Amount:'.$response->getTransaction()->getAuthAmount());
            nlog('                   Trans ID:'.$response->getTransaction()->getTransId());
        } else {
            nlog("ERROR :  Invalid response\n");
            $errorMessages = $response->getMessages()->getMessage();
            nlog('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText());
        }

        return $response;
    }
}
