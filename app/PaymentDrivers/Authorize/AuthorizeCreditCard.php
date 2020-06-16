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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\SystemLog;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\PaymentDrivers\Authorize\AuthorizeCreateCustomer;
use App\PaymentDrivers\Authorize\ChargePaymentProfile;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;

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

        $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($client_gateway_token->gateway_customer_reference, $client_gateway_token->token, $request->input('amount'));

        $this->handleResponse($data, $request);
    }

    private function handleResponse($data, $request)
    {
        //info(print_r( $response->getTransactionResponse()->getMessages(),1));
        
        $response = $data['response'];

        if($response != null && $response->getMessages()->getResultCode() == "Ok")
            return $this->processSuccessfulResponse($data, $request);

        return $this->processFailedResponse($data, $request);
    }

    private function processSuccessfulResponse($data, $request)
    {
        $response = $data['response'];
        //create a payment record and fire notifications and then return 

        $payment = PaymentFactory::create($this->authorize->client->company_id, $this->authorize->client->user_id);
        $payment->client_id = $this->client->id;
        $payment->company_gateway_id = $this->authorize->company_gateway->id;
        $payment->status_id = Payment::STATUS_PAID;
        $payment->currency_id = $this->authorize->client->getSetting('currency_id');
        $payment->date = Carbon::now();
        $payment->transaction_reference = $response->getTransactionResponse()->getTransId();
        $payment->amount = $request->input('amount'); 
        $payment->currency_id = $this->authorize->client->id;
        $payment->save();

        $this->authorize->attachInvoices($payment, $request->hashed_ids);

       $payment->service()->updateInvoicePayment();

        event(new PaymentWasCreated($payment, $payment->company));

        $logger_message = [
            'server_response' => $response->getTransactionResponse()->getTransId(),
            'data' => $this->formatGatewayResponse($data, $request)
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_AUTHORIZE, $this->client);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }

    private function processFailedResponse($data, $request)
    {

    }

    private function formatGatewayResponse($data, $request)
    {
        $response = $data['response'];

        return [
            'transaction_reference' => $response->getTransactionResponse()->getTransId(),
            'amount' => $request->input('amount'),
            'code' => $response->getTransactionResponse()->getCode(),
            'description' => $response->getTransactionResponse()->getDescription(),
            'auth_code' => $response->getTransactionResponse()->getAuthCode(),
            'invoices' => $request->hashed_ids,

        ];
    }

}