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
use App\PaymentDrivers\AuthorizePaymentDriver;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class AuthorizeCreditCard
{
    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function processPaymentView($data)
    {
    	$tokens = ClientGatewayToken::where('client_id', $this->authorize->client->id)
    								->where('company_gateway_id', $this->authorize->company_gateway->id)
    								->where('gateway_type_id', $this->authorize->payment_method_id)
    								->get();

		$data['tokens'] = $tokens;
		$data['gateway'] = $this->authorize->company_gateway;
		$data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
		$data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

		return render('gateways.authorize.credit_card_payment', $data);
        
    }

    public function processPaymentResponse($response)
    {

    }

}