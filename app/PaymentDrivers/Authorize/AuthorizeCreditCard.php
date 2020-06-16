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

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\PaymentDrivers\Authorize\AuthorizeCreateCustomer;
use App\PaymentDrivers\Authorize\ChargePaymentProfile;
use App\Utils\Traits\MakesHash;

/**
 * Class AuthorizeCreditCard
 * @package App\PaymentDrivers\Authorize
 *
 */
class AuthorizeCreditCard
{
    use MakesHash;

    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function processPaymentView($data)
    {
    	$tokens = ClientGatewayToken::where('client_id', $this->authorize->client->id)
    								->where('company_gateway_id', $this->authorize->company_gateway->id)
    								->where('gateway_type_id', GatewayType::CREDIT_CARD)
    								->get();

		$data['tokens'] = $tokens;
		$data['gateway'] = $this->authorize->company_gateway;
		$data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
		$data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

		return render('gateways.authorize.credit_card_payment', $data);

    }

    public function processPaymentResponse($request)
    {
        if($request->token)
            return $this->processTokenPayment($request);

        $data = $request->all();
        
        dd($data);

        $authorise_payment_method = new AuthorizeCreateCustomer($this->authorize, $this->authorize->client);

        $gateway_customer_reference = $authorise_payment_method->create($data);
        
        info($gateway_customer_reference);

        $payment_profile = $authorise_payment_method->addPaymentMethodToClient($gateway_customer_reference, $data);

        if($data['save_payment_method'] == true)
            $client_gateway_token = $authorise_payment_method->createClientGatewayToken($payment_profile, $gateway_customer_reference);

        return (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($gateway_customer_reference, $payment_profile, $data['amount_with_fee']);

    }

    private function processTokenPayment($request)
    {
        $client_gateway_token = ClientGatewayToken::find($this->decodePrimaryKey($request->token));

        $response = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($client_gateway_token->gateway_customer_reference, $client_gateway_token->token, $request->input('amount'));
        
    }

    private function handleResponse($response)
    {

    }

}