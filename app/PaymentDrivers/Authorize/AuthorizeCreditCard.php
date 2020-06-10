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

use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\PaymentDrivers\Authorize\AuthorizeCreateCustomer;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class AuthorizePaymentMethod
{
    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function authorizeView($payment_method)
    {

        switch ($payment_method) {
            case GatewayType::CREDIT_CARD:
                return $this->authorizeCreditCard();
                break;
            case GatewayType::BANK_TRANSFER:
                return $this->authorizeBankTransfer();
                break;

            default:
                # code...
                break;
        }

    }

    public function authorizeResponseView($payment_method, $data)
    {

        switch ($payment_method) {
            case GatewayType::CREDIT_CARD:
                return $this->authorizeCreditCardResponse($data);
                break;
            case GatewayType::BANK_TRANSFER:
                return $this->authorizeBankTransferResponse($data);
                break;

            default:
                # code...
                break;
        }

    }

    public function authorizeCreditCard()
    {
        $data['gateway'] = $this->authorize->company_gateway;
        $data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
        $data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

        return render('gateways.authorize.add_credit_card', $data);
    }

    public function authorizeBankTransfer()
    {
        
    }

    public function authorizeCreditCardResponse($data)
    {

        if($client_gateway_token_record = $this->authorize->findClientGatewayRecord())
            $this->addCreditCardToClient($client_gateway_token_record, $data);
        else{
            $client_gateway_token_record = (new AuthorizeCreateCustomer($this->authorize))->create($data);
            $this->addCreditCardToClient($client_gateway_token_record, $data);
        }

        return redirect()->route('client.payment_methods.index');

    }

    public function authorizeBankTransferResponse($data)
    {
        
    }

    private function addCreditCardToClient(ClientGatewayToken $client_gateway_token, $data)
    {
        //add a payment profile to the client profile

        //we only use the $client_gateway_token record as a reference to create a NEW client_gateway_token for this gateway
    }
    
}
