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
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\EasymerchantPaymentDriver;
use App\PaymentDrivers\Easymerchant\Utilities;
use Illuminate\Support\Facades\Http;

class CreditCard
{
    use Utilities;
    /**
     * @var EasymerchantPaymentDriver
     */
    private $easymerchant;

    public function __construct(EasymerchantPaymentDriver $easymerchant)
    {
        $this->easymerchant = $easymerchant;

        $this->easymerchant->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->easymerchant;
        $data['threeds_enable'] = $this->easymerchant->company_gateway->getConfigField('threeds') ? "true" : "false";
        $data['public_client_id'] = 1;//$this->authorize->init()->getPublicClientKey();
        $data['api_login_id'] = $this->easymerchant->company_gateway->getConfigField('apiLoginId');

        return render('gateways.easymerchant.credit_card.authorize', $data);
    }

    public function authorizeResponse($data): \Illuminate\Http\RedirectResponse
    {
        $postData = $this->getHeaders();

        $card_data = $this->getCustomerCardData($data, $postData['api_url']);

        try {

            $response = Http::withHeaders($postData['headers'])->post($card_data['api_url'], $card_data);

            $result = $response->json();

            if($result['status']){

                $store_data = [
                    'expirationMonth' => $card_data['exp_month'],
                    'expirationYear' => $this->formatExpiryYear($data['expiry-year']),
                    'last4' => $result['card_last_4'],
                    'cardType' => 'Visa',
                    'token' => $result['card_id']
                ];

                $customer_reference = (array_key_exists('customer', $card_data)) ? $card_data['customer'] : $result['customer_id']; 
                $store = $this->storePaymentMethod($store_data , $customer_reference);
                return redirect()->route('client.payment_methods.index');
            }

            $this->easymerchant->sendFailureMail($result['message']);

            throw new PaymentFailed($e->getMessage(), 500);
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
        $data['gateway'] = $this->easymerchant;

        return render('gateways.easymerchant.credit_card.pay', $data);
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

        $data = $this->getCustomerCardData($request->all());
        $data['description'] = $this->easymerchant->getDescription(false);

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

        $this->easymerchant->logSuccessfulGatewayResponse(['response' => $request['message'], 'data' => $this->easymerchant->payment_hash], SystemLog::TYPE_EASYMERCHANT);
        //save card functionality
        if($request->has('save_card') && $request->save_card){
            $store_data = [
                'expirationMonth' => $request['expiry-month'],
                'expirationYear' => $this->formatExpiryYear($request['expiry-year']),
                'last4' => substr($this->removeBlankSpace($request['card-number']), -4),
                'cardType' => 'Visa',
                'token' => $result['card_id']
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
            SystemLog::TYPE_EASYMERCHANT,
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
            SystemLog::TYPE_EASYMERCHANT,
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
