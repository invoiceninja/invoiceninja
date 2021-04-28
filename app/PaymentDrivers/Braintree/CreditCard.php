<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\Braintree;


use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Request;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\BraintreePaymentDriver;

class CreditCard
{
    /**
     * @var BraintreePaymentDriver
     */
    private $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->braintree;

        return render('gateways.braintree.credit_card.authorize', $data);
    }

    public function authorizeResponse($data)
    {

    }

    /**
     * Credit card payment page.
     *
     * @param array $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->braintree;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return render('gateways.braintree.credit_card.pay', $data);
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
        $state = [
            'server_response' => json_decode($request->gateway_response),
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->braintree->payment_hash->data = array_merge((array)$this->braintree->payment_hash->data, $state);
        $this->braintree->payment_hash->save();

        $result = $this->braintree->gateway->transaction()->sale([
            'amount' => '2000.50',
            'paymentMethodNonce' => $state['token'],
            'deviceData' => $state['client-data'],
            'options' => [
                'submitForSettlement' => true
            ],
        ]);

        if ($result->success) {
            $this->braintree->logSuccessfulGatewayResponse(['response' => $request->server_response, 'data' => $this->braintree->payment_hash], SystemLog::TYPE_BRAINTREE);

            return $this->processSuccessfulPayment($result);
        }

        return $this->processUnsuccessfulPayment($result);
    }

    private function processSuccessfulPayment($response)
    {
        $state = $this->braintree->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::parseCardType(strtolower($state->server_response->details->cardType)),
            'amount' => 10,
            'transaction_reference' => $response->transaction->id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->braintree->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client
        );

        return redirect()->route('client.payments.show', ['payment' => $this->braintree->encodePrimaryKey($payment->id)]);
    }

    /**
     * @throws PaymentFailed
     */
    private function processUnsuccessfulPayment($response)
    {
        PaymentFailureMailer::dispatch($this->braintree->client, $response->transaction->additionalProcessorResponse, $this->braintree->client->company, 10);

        PaymentFailureMailer::dispatch(
            $this->braintree->client,
            $response,
            $this->braintree->client->company,
            10,
        );

        $message = [
            'server_response' => $response,
            'data' => $this->braintree->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client
        );

        throw new PaymentFailed($response->transaction->additionalProcessorResponse, $response->transaction->processorResponseCode);
    }
}
