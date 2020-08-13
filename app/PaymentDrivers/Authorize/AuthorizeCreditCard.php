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
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\PaymentDrivers\Authorize\AuthorizeCreateCustomer;
use App\PaymentDrivers\Authorize\AuthorizePaymentMethod;
use App\PaymentDrivers\Authorize\ChargePaymentProfile;
use App\Utils\Ninja;
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
        
        $authorise_create_customer = new AuthorizeCreateCustomer($this->authorize, $this->authorize->client);

        $gateway_customer_reference = $authorise_create_customer->create($data);
        
        info($gateway_customer_reference);

        $authorise_payment_method = new AuthorizePaymentMethod($this->authorize);

        $payment_profile = $authorise_payment_method->addPaymentMethodToClient($gateway_customer_reference, $data);
        $payment_profile_id = $payment_profile->getPaymentProfile()->getCustomerPaymentProfileId();

        info($request->input('store_card'));
        
        if($request->has('store_card') && $request->input('store_card') === 'true'){
            $authorise_payment_method->payment_method = GatewayType::CREDIT_CARD;
            $client_gateway_token = $authorise_payment_method->createClientGatewayToken($payment_profile, $gateway_customer_reference);
        }

        $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($gateway_customer_reference, $payment_profile_id, $data['amount_with_fee']);

        return $this->handleResponse($data, $request);

    }

    private function processTokenPayment($request)
    {
        $client_gateway_token = ClientGatewayToken::find($this->decodePrimaryKey($request->token));

        $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($client_gateway_token->gateway_customer_reference, $client_gateway_token->token, $request->input('amount_with_fee'));

        return $this->handleResponse($data, $request);
    }

    private function tokenBilling($cgt, $amount, $invoice)
    {
        $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($cgt->gateway_customer_reference, $cgt->token, $amounts);

        if($data['response'] != null && $data['response']->getMessages()->getResultCode() == "Ok") {

            $payment = $this->createPaymentRecord($data, $amount);

            $this->authorize->attachInvoices($payment, $invoice->hashed_id);
            
            event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

            $vars = [
                'hashed_ids' => $invoice->hashed_id,
                'amount' => $amount
            ];

            $logger_message = [
                'server_response' => $response->getTransactionResponse()->getTransId(),
                'data' => $this->formatGatewayResponse($data, $vars)
            ];

            SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_AUTHORIZE, $this->authorize->client);

            return true;            
        }
        else {


            return false;
        }

    }
    
    private function handleResponse($data, $request)
    {        
        $response = $data['response'];

        if($response != null && $response->getMessages()->getResultCode() == "Ok")
            return $this->processSuccessfulResponse($data, $request);

        return $this->processFailedResponse($data, $request);
    }

    private function createPaymentRecord($data, $amount) :?Payment
    {

        $response = $data['response'];
        //create a payment record 

        $payment = PaymentFactory::create($this->authorize->client->company_id, $this->authorize->client->user_id);
        $payment->client_id = $this->authorize->client->id;
        $payment->company_gateway_id = $this->authorize->company_gateway->id;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->gateway_type_id = GatewayType::CREDIT_CARD;
        $payment->type_id = PaymentType::CREDIT_CARD_OTHER;
        $payment->currency_id = $this->authorize->client->getSetting('currency_id');
        $payment->date = Carbon::now();
        $payment->transaction_reference = $response->getTransactionResponse()->getTransId();
        $payment->amount = $amount; 
        $payment->currency_id = $this->authorize->client->getSetting('currency_id');
        $payment->client->getNextPaymentNumber($this->authorize->client);
        $payment->save();

        return $payment;
    }

    private function processSuccessfulResponse($data, $request)
    {
        $payment = $this->createPaymentRecord($data, $request->input('amount_with_fee'));

        $this->authorize->attachInvoices($payment, $request->hashed_ids);

        $payment->service()->updateInvoicePayment();

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        $vars = [
            'hashed_ids' => $request->input('hashed_ids'),
            'amount' => $request->input('amount')
        ];

        $logger_message = [
            'server_response' => $response->getTransactionResponse()->getTransId(),
            'data' => $this->formatGatewayResponse($data, $vars)
        ];

        SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_AUTHORIZE, $this->authorize->client);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

    }

    private function processFailedResponse($data, $request)
    {   
        //dd($data);
        info(print_r($data,1));
    }

    private function formatGatewayResponse($data, $vars)
    {
        $response = $data['response'];

        return [
            'transaction_reference' => $response->getTransactionResponse()->getTransId(),
            'amount' => $vars['amount'],
            'auth_code' => $response->getTransactionResponse()->getAuthCode(),
            'code' => $response->getTransactionResponse()->getMessages()[0]->getCode(),
            'description' => $response->getTransactionResponse()->getMessages()[0]->getDescription(),
            'invoices' => $vars['hashed_ids'],
        ];
    }

}