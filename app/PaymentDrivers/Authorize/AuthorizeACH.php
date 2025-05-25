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

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\PaymentType as PType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\BankAccountType;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use net\authorize\api\contract\v1\CustomerAddressType;
use App\PaymentDrivers\Authorize\AuthorizePaymentMethod;
use net\authorize\api\contract\v1\CustomerPaymentProfileType;
use net\authorize\api\contract\v1\CreateCustomerPaymentProfileRequest;
use net\authorize\api\controller\CreateCustomerPaymentProfileController;

class AuthorizeACH implements LivewireMethodInterface
{
    use MakesHash;

    public function __construct(public AuthorizePaymentDriver $authorize)
    {
    }
      
    /**
     * livewirePaymentView
     *
     * @param  array $data
     * @return string
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.authorize.ach.pay_livewire';
    }
    
    /**
     * paymentData
     *
     * @param  array $data
     * @return array
     */
    public function paymentData(array $data): array
    {
  
        $tokens = ClientGatewayToken::where('client_id', $this->authorize->client->id)
                ->where('company_gateway_id', $this->authorize->company_gateway->id)
                ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
                ->orderBy('is_default', 'desc')
                ->get();


        $data['tokens'] = $tokens;
        $data['gateway'] = $this->authorize;
        $data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
        $data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

        return $data;
    }
    
    /**
     * processPaymentView
     *
     * @param  array $data
     * @return void
     */
    public function processPaymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.authorize.ach.pay', $data);
    }
    
    /**
     * tokenBilling
     *
     * @param  mixed $cgt
     * @param  mixed $payment_hash
     * @return void
     */
    public function tokenBilling($cgt, $payment_hash)
    {
        $cc = new AuthorizeCreditCard($this->authorize);
        return $cc->tokenBilling($cgt, $payment_hash);
    }
    
    /**
     * processPaymentResponse
     *
     * @param  mixed $request
     * @return void
     */
    public function processPaymentResponse($request)
    {

        $this->authorize->init();

        if($request->token) {
            $client_gateway_token = ClientGatewayToken::query()
                ->where('id', $this->decodePrimaryKey($request->token))
                ->first();
        }
        else{    
            $data = $request->all();
            
            $data['is_running_payment'] = true;
            $data['gateway_type_id'] = \App\Models\GatewayType::BANK_TRANSFER;
            $client_gateway_token = (new AuthorizePaymentMethod($this->authorize))->authorizeBankTransferResponse($data);
    
            if(!$client_gateway_token) {
                throw new PaymentFailed('Could not find the payment profile', 400);
            }
        }

        $payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();

        $data = (new ChargePaymentProfile($this->authorize))
            ->chargeCustomerProfile($client_gateway_token->gateway_customer_reference, $client_gateway_token->token, $payment_hash->data->amount_with_fee);

        $response = $data['raw_response'];

        if ($response->getMessages()->getResultCode() == 'Ok') {

            $payment = $this->createPayment($payment_hash, $response);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $payment_hash->data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_AUTHORIZE,
                $this->authorize->client,
                $this->authorize->client->company,
            );

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

        }

        $error_messages = $response->getMessages()->getMessage();
        $error = $error_messages[0]->getText();

        $this->authorize->sendFailureMail($error);

        throw new PaymentFailed($error, 400);
    }

    private function createPayment($payment_hash, $response)
    {
        $data = [
            'payment_method' => PType::BANK_TRANSFER,
            'payment_type' => PType::BANK_TRANSFER,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->getTransactionResponse()->getTransId(),
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        return $this->authorize->createPayment($data, Payment::STATUS_COMPLETED);
    }

} 