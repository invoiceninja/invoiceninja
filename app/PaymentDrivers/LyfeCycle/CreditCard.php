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

namespace App\PaymentDrivers\LyfeCycle;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\LyfeCyclePaymentDriver;
use App\PaymentDrivers\LyfeCycle\Utilities;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CreditCard
{
    use Utilities;
    /**
     * @var LyfeCyclePaymentDriver
     */
    private $easymerchant;

    public function __construct(LyfeCyclePaymentDriver $easymerchant)
    {
        $this->easymerchant = $easymerchant;

        $this->easymerchant->init();
    }

    public function authorizeView(array $data)
    {
        $postData = $this->getHeaders();
        $data['gateway'] = $this->easymerchant;

        $customer = $this->checkCustomerExists();
        $data['customer'] = $customer ? : NULL;
        $data['url'] = $postData['api_url'].'/card';
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;
        $data['publish_key'] = $this->getPublishKey();

        if($data['customer'] == NULL){
            $customer_input = $this->findOrCreateCustomer($postData['api_url'], $postData['headers']);
            if($customer_input['status']){
                $data['customer'] = $customer_input['data']['customer'];
            }else{
                throw new PaymentFailed($customer_input['message'], 500);
            }
        }

        return render('gateways.easymerchant.credit_card.authorize', $data);
    }

    public function authorizeResponse($data): \Illuminate\Http\RedirectResponse
    {
        try {

            $store_data = [
                'expirationMonth' => $data['expiry-month'],
                'expirationYear' => $this->formatExpiryYear($data['expiry-year']),
                'last4' => $data['card-number'],
                'cardType' => 'Visa',
                'token' => $data['payment_intent']
            ];

            $store = $this->storePaymentMethod($store_data , $data['customer']);
            return redirect()->route('client.payment_methods.index');

        } catch (\Exception $e) {

            if ($e instanceof \Easymerchant\Exception\Authorization) {
                $this->easymerchant->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->easymerchant->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    public function paymentView(array $data)
    {
        $tokens = ClientGatewayToken::where('client_id', $this->easymerchant->client->id)
                    ->where('company_gateway_id', $this->easymerchant->company_gateway->id)
                    ->where('gateway_type_id', GatewayType::CREDIT_CARD)
                    ->orderBy('is_default', 'desc')
                    ->get();

        $data['tokens'] = $tokens;
        $data['gateway'] = $this->easymerchant;
        $customer = $this->checkCustomerExists();
        $data['customer'] = $customer ? : NULL;
        $currency = 'usd';
        if($data['client']->currency() && $data['client']->currency()->code){
            $currency = $data['client']->currency()->code;
        }

        $postData = $this->getHeaders();
        // create new customer if not added to easymerchant
        if($data['customer'] == NULL){
            $customer_input = $this->findOrCreateCustomer($postData['api_url'], $postData['headers']);
            if($customer_input['status']){
                $data['customer'] = $customer_input['data']['customer'];
            }else{
                throw new PaymentFailed($customer_input['message'], 500);
            }
        }
        $api_url = $postData['api_url'].'/paymentintent/add';

        try {
            $params = ['currency' => $currency, 'amount' => $data['amount_with_fee'], 'payment_type' => 'card'];

            $response = Http::withHeaders($postData['headers'])->post($api_url, $params);

            $result = $response->json();

        } catch (\Exception $e) {
            if ($e instanceof \Easymerchant\Exception\Authorization) {
                $this->easymerchant->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->easymerchant->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        if($result['status']){

            $data['payment_intent'] = $result['payment_intent'];
            $data['url'] = $postData['api_url'].'/card';
            $data['publish_key'] = $this->getPublishKey();

            return render('gateways.easymerchant.credit_card.pay', $data);
        }else{
            throw new PaymentFailed($result['message'], 404);
        }
    }

    /**
     * Process the credit card payments.
     *
     * @param PaymentResponseRequest $request
     * @return \Illuminate\Http\RedirectResponse|void
     * @throws PaymentFailed
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->easymerchant->client->fresh();

        $state = [
            'server_response' => json_decode($request->gateway_response),
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->easymerchant->payment_hash->data = array_merge((array) $this->easymerchant->payment_hash->data, $state);
        $this->easymerchant->payment_hash->save();

        $postData = $this->getHeaders();

        $data = $this->cardChargeDetails($request->all());
        $data['description'] = $this->easymerchant->getDescription(false);
        $data['amount'] = $this->formatAmount($this->easymerchant->payment_hash->data->amount_with_fee);

        $api_url = $postData['api_url'].'/charges';

        try {

            $response = Http::withHeaders($postData['headers'])->post($api_url, $data);

            $result = $response->json();

        } catch (\Exception $e) {
            if ($e instanceof \Easymerchant\Exception\Authorization) {
                $this->easymerchant->sendFailureMail(ctrans('texts.generic_gateway_error'));

                throw new PaymentFailed(ctrans('texts.generic_gateway_error'), $e->getCode());
            }

            $this->easymerchant->sendFailureMail($e->getMessage());

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        if(empty($result) || empty($result['status'])) {
            $error = !empty($result) && !empty($result['message']) ? $result['message'] : 'Undefined gateway error';
            $responseCode = $response?->status() ?: 500;
            return $this->processUnsuccessfulPayment($error, $responseCode);
        }

        $this->easymerchant->logSuccessfulGatewayResponse(['response' => $request['message'], 'data' => $this->easymerchant->payment_hash], SystemLog::TYPE_LYFECYCLE);
        //save card functionality
        if($request->has('save_card') && $request->save_card){
            $store_data = [
                'expirationMonth' => $request['expiry-month'],
                'expirationYear' => $this->formatExpiryYear($request['expiry-year']),
                'last4' => substr($this->removeBlankSpace($request['card-number']), -4),
                'cardType' => 'Visa',
                'token' => $result['payment_intent']
            ];

            $customer_reference = (array_key_exists('customer_id', $result)) ? $result['customer_id'] : $data['customer']; 
            $store = $this->storePaymentMethod($store_data , $customer_reference);
        }

        return $this->processSuccessfulPayment($result);
    }

    private function processSuccessfulPayment($response)
    {
        $state = $this->easymerchant->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::parseCardType('Visa Card'),
            'amount' => $this->easymerchant->payment_hash->data->amount_with_fee,
            'transaction_reference' => $response['charge_id'],
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->easymerchant->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_LYFECYCLE,
            $this->easymerchant->client,
            $this->easymerchant->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->easymerchant->encodePrimaryKey($payment->id)]);
    }

    /**
     * @throws PaymentFailed
     */
    private function processUnsuccessfulPayment($error, $responseCode)
    {
        $this->easymerchant->sendFailureMail($error);

        $message = [
            'server_response' => $error,
            'data' => $this->easymerchant->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_LYFECYCLE,
            $this->easymerchant->client,
            $this->easymerchant->client->company,
        );

        throw new PaymentFailed($error ?: 'Unhandled error, please contact merchant', $responseCode ?: 500);
    }

    private function storePaymentMethod($method, $customer_reference)
    {
        try {
            $payment_meta = new \stdClass;
            $payment_meta->exp_month = (string) $method['expirationMonth'];
            $payment_meta->exp_year = (string) $method['expirationYear'];
            $payment_meta->brand = (string) $method['cardType'];
            $payment_meta->last4 = (string) $method['last4'];
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method['token'],
                'payment_method_id' => GatewayType::CREDIT_CARD
            ];

            $this->easymerchant->storeGatewayToken($data, ['gateway_customer_reference' => $customer_reference]);
        } catch (\Exception $e) {
            return $this->easymerchant->processInternallyFailedPayment($this->easymerchant, $e);
        }
    }
}
