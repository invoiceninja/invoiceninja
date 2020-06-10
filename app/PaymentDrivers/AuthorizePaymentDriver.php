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

use App\Models\ClientGatewayToken;
use App\PaymentDrivers\Authorize\AuthorizeCreditCard;
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

    public function authorizeView($payment_method)
    {
        return (new AuthorizePaymentMethod($this))->authorizeView($payment_method);
    }

    public function authorizeResponse($payment_method, array $data)
    {

        // <input type="hidden" name="is_default" id="is_default">
        // <input type="hidden" name="dataValue" id="dataValue" />
        // <input type="hidden" name="dataDescriptor" id="dataDescriptor" />


        // $client_gateway_token = new ClientGatewayToken();
        // $client_gateway_token->company_id = $this->stripe->client->company->id;
        // $client_gateway_token->client_id = $this->stripe->client->id;
        // $client_gateway_token->token = $payment_method;
        // $client_gateway_token->company_gateway_id = $this->stripe->company_gateway->id;
        // $client_gateway_token->gateway_type_id = $gateway_type_id;
        // $client_gateway_token->gateway_customer_reference = $customer->id;
        // $client_gateway_token->meta = $payment_meta;
        // $client_gateway_token->save();

    }

    // public function fire()
    // {

    //     $controller = new CreateTransactionController($this->anet);
    //     $response = $controller->executeWithApiResponse($env);

    //     return $response;
    // }


    public function authorize($payment_method) 
    {

    }
    
    public function purchase() 
    {

    }

    public function refund() 
    {

    }

    private function findClientGatewayRecord() :?ClientGatewayToken
    {
        return ClientGatewayToken::where('client_id', $this->client->id)
                                 ->where('company_gateway_id', $this->company_gateway->id)
                                 ->first();
    }
}
