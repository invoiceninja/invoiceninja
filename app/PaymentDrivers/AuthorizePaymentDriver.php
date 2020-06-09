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
use net\authorize\api\contract\v1\GetMerchantDetailsRequest;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\controller\GetMerchantDetailsController;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class AuthorizePaymentDriver extends BaseDriver
{

    public $merchant_authentication;

    public function init()
    {
        error_reporting (E_ALL & ~E_DEPRECATED);

        $this->merchant_authentication = new MerchantAuthenticationType();
        $this->merchant_authentication->setName($this->company_gateway->getConfigField('apiLoginId'));
        $this->merchant_authentication->setTransactionKey($this->company_gateway->getConfigField('transactionKey'));

        return $this;
    }

    public function getPublicClientKey()
    {

        $request = new GetMerchantDetailsRequest();
        $request->setMerchantAuthentication($this->merchant_authentication);

        $controller = new GetMerchantDetailsController($request);
        $response = $controller->executeWithApiResponse($this->mode());

        return $response->getPublicClientKey();

    }

    private function mode()
    {

        if($this->company_gateway->getConfigField('testMode'))
            return  ANetEnvironment::SANDBOX;
        
        return $env = ANetEnvironment::PRODUCTION;

    }

    public function authorizeView()
    {
        $data['gateway'] = $this->company_gateway;
        $data['public_client_id'] = $this->init()->getPublicClientKey();
        $data['api_login_id'] = $this->company_gateway->getConfigField('apiLoginId');

        return render('gateways.authorize.add_credit_card', $data);
    }

    // public function fire()
    // {

    //     $controller = new CreateTransactionController($this->anet);
    //     $response = $controller->executeWithApiResponse($env);

    //     return $response;
    // }


    public function authorize() {}
    
    public function purchase() {}

    public function refund() {}

    
}
