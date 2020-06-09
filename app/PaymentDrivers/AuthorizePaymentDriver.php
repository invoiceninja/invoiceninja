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

namespace App\PaymentDrivers;

use net\authorize\api\constants\ANetEnvironment;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\controller\CreateTransactionController;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class AuthorizePaymentDriver extends BaseDriver
{

    public $anet;

    public function init()
    {

        $merchantAuthentication = new MerchantAuthenticationType();
        $merchantAuthentication->setName($this->company_gateway->getConfigField('apiLoginId'));
        $merchantAuthentication->setTransactionKey($this->company_gateway->getConfigField('transactionKey'));


        $this->anet = new CreateTransactionRequest();
        $this->anet->setMerchantAuthentication($merchantAuthentication);

        return $this;
    }

    public function fire()
    {

        if($this->company_gateway->getConfigField('testMode'))
            $env = ANetEnvironment::SANDBOX;
        else
            $env = ANetEnvironment::PRODUCTION;

        $controller = new CreateTransactionController($this->anet);
        $response = $controller->executeWithApiResponse($env);

        return $response;
    }


    public function authorize() {}
    
    public function purchase() {}

    public function refund() {}

    
}
