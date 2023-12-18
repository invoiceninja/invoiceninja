<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Easymerchant;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\EasymerchantPaymentDriver;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Easymerchant\Utilities;
use Illuminate\Support\Facades\Http;

use Exception;

class ACH
{
    use MakesHash, Utilities;

    /** @var EasymerchantPaymentDriver */
    public $easymerchant;

    public function __construct(EasymerchantPaymentDriver $easymerchant)
    {
        $this->easymerchant = $easymerchant;
    }

    /**
     * Authorize a bank account - requires microdeposit verification
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->easymerchant;

        return render('gateways.easymerchant.ach.authorize', array_merge($data));
    }

    public function authorizeResponse(Request $request)
    {
        $this->easymerchant->init();

        $postData = $this->getHeaders();
        $ach_data = $this->getACHCustomer($request);

        $source = [
            'token' => null,
            'account_type' => 'checking',
            'account_number' => $request->account_number,
            'routing_number' => $request->routing_number,
            'business_account' => ($request->business_account == 'individual') ? 0 : 1,
            'gateway_type_id' => $request->gateway_type_id,
            'company_gateway_id' => $request->company_gateway_id
        ];

        if(array_key_exists('create_customer', $ach_data) && $ach_data['create_customer'] == "1") {

            try {
                $customer_url = $postData['api_url'].'/customers';

                $response = Http::withHeaders($postData['headers'])->post($customer_url, $ach_data);
                
                $result = $response->json();
                
                if($result['status']){
                    $ach_data['customerId'] = $result['customer_id'];
                }
            } catch (\Exception $e) {
                $this->easymerchant->sendFailureMail($e->getMessage());

                throw new PaymentFailed($e->getMessage(), $e->getCode());
            }
        }

        try {
            $api_url = $postData['api_url'].'/ach/account';

            $response = Http::withHeaders($postData['headers'])->post($api_url, $ach_data);

            $result = $response->json();

            if($result['status']){

                $source['token'] = $result['account_id'];
                $source['gateway_customer_reference'] = $ach_data['customerId'];
                $customer_reference = (array_key_exists('customerId', $ach_data)) ? $ach_data['customerId'] : null; 
                $this->storeACHPaymentMethod($source, $request->input('method'), $customer_reference);

                return redirect()->route('client.payment_methods.index');
            }

        } catch (\Exception $e) {

            if ($e instanceof \Easymerchant\Exception\Authorization) {
                $this->easymerchant->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->easymerchant->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Make a payment WITH instant verification.
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->easymerchant;
        $data['currency'] = $this->easymerchant->client->getCurrencyCode();
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['customer'] = null;//$this->easymerchant->findOrCreateCustomer();
        $data['amount'] = $data['total']['amount_with_fee'];

        $description = $this->easymerchant->getDescription(false);

        $intent = false;

        $data['client_secret'] = $intent ? $intent->client_secret : false;

        return render('gateways.easymerchant.ach.pay', $data);
    }

    public function paymentResponse($request)
    {
        $this->easymerchant->init();

        $postData = $this->getHeaders();

        $paymentData = $this->getACHPaymentDetails($request);

        $state = [
            'payment_method' => $request->payment_method_id,
            'gateway_type_id' => $request->company_gateway_id,
            'amount' => $this->formatAmount($request->amount),
            'currency' => 'usd',
            // 'customer' => $request->customer,
        ];

        $state = array_merge($state, $request->all());
        $state['status'] = 'paid_unsettled';

        $this->easymerchant->payment_hash->data = array_merge((array) $this->easymerchant->payment_hash->data, $state);
        $this->easymerchant->payment_hash->save();

        $paymentData['description'] = $this->easymerchant->getDescription(false);
        $paymentData['amount'] = $this->formatAmount($request->amount);
        $paymentData['payment_method_id'] = $request->payment_method_id;

        try {
            $api_url = $postData['api_url'].'/ach/charge';

            $response = Http::withHeaders($postData['headers'])->post($api_url, $paymentData);

            $result = $response->json();

            if($result['status']){
                $paymentData['charge'] = $result['charge_id'];
                if(array_key_exists('account_id', $result)){
                    $paymentData['token'] = $result['account_id'];
                }
                if(array_key_exists('customer_id', $result)){
                    $paymentData['customer'] = $result['customer_id'];
                }
                $this->processSuccessfulPayment($paymentData);
            }else{
                throw new PaymentFailed($result['message'], 500);
                return false;
            }

        } catch (\Exception $e) {

            if ($e instanceof \Easymerchant\Exception\Authorization) {
                $this->easymerchant->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->easymerchant->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
        return redirect()->route('client.payments.process');
    }

    public function processUnsuccessfulPayment($state)
    {
        $this->easymerchant->sendFailureMail($state['charge']);

        $message = [
            'server_response' => $state['charge'],
            'data' => $this->easymerchant->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_EASYMERCHANT,
            $this->easymerchant->client,
            $this->easymerchant->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    private function processSuccessfulPayment($response)
    {
        $state = $this->easymerchant->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::ACH,
            'amount' => $response['amount'],
            'transaction_reference' => $response['charge'],
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->easymerchant->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_EASYMERCHANT,
            $this->easymerchant->client,
            $this->easymerchant->client->company,
        );
        $customer = null;
        if($response['save_account']){
            
            if(array_key_exists('customer', $response)){
                $customer = $response['customer'];
            }
            $this->storeACHPaymentMethod($response,$response['payment_method_id'], $customer);
        }

        return redirect()->route('client.payments.show', ['payment' => $this->easymerchant->encodePrimaryKey($payment->id)]);
    }

    private function storeACHPaymentMethod($method, $payment_method_id='', $customer=null)
    {
        $last4 = substr($method['account_number'], -4);
        try {
            $payment_meta = new \stdClass;
            $payment_meta->account_number = (string) $method['account_number'];
            $payment_meta->routing_number = (string) $method['routing_number'];
            $payment_meta->account_type = 'checking';
            $payment_meta->brand = $this->easymerchant->company_gateway->label. ' - (ACH)';
            $payment_meta->last4 = (string) $last4;
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->business_account = $method['business_account'];

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method['token'],
                'payment_method_id' => $payment_method_id,
            ];

            return $this->easymerchant->storeGatewayToken($data, ['gateway_customer_reference' => $customer]);
        } catch (Exception $e) {
            return $this->easymerchant->processInternallyFailedPayment($this->easymerchant, $e);
        }
    }
}
