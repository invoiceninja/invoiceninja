<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Http\Requests\Gateways\Mollie\Mollie3dsRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Mollie\CreditCard;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Validator;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;

class MolliePaymentDriver extends BaseDriver
{
    use MakesHash;

    /**
     * @var boolean
     */
    public $refundable = true;

    /**
     * @var true
     */
    public $token_billing = true;

    /**
     * @var true
     */
    public $can_authorise_credit_card = true;

    /**
     * @var MollieApiClient
     */
    public $gateway;

    /**
     * @var mixed
     */
    public $payment_method;

    /**
     * @var string[]
     */
    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_MOLLIE;

    public function init(): self
    {
        $this->gateway = new MollieApiClient();

        $this->gateway->setApiKey(
            $this->company_gateway->getConfigField('apiKey'),
        );

        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        return $this->payment_method->yourRefundImplementationHere();
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->payment_method->yourTokenBillingImplmentation();
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'starts_with:tr'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        
        $this->init();

        $codes = [
            'open' => Payment::STATUS_PENDING,
            'canceled' => Payment::STATUS_CANCELLED,
            'pending' => Payment::STATUS_PENDING,
            'expired' => Payment::STATUS_CANCELLED,
            'failed' => Payment::STATUS_FAILED,
            'paid' => Payment::STATUS_COMPLETED,
        ];

        try {
            $payment = $this->gateway->payments->get($request->id);

            $record = Payment::where('transaction_reference', $payment->id)->firstOrFail();
            $record->status_id = $codes[$payment->status];
            $record->save();

            return response()->json([], 200);
        } catch(ApiException $e) {
            return response()->json(['message' => $e->getMessage(), 'gatewayStatusCode' => $e->getCode()], 500);
        }
    }

    public function process3dsConfirmation(Mollie3dsRequest $request)
    {
        $this->init();

        $this->setPaymentHash($request->getPaymentHash());

        try {
            $payment = $this->gateway->payments->get($request->getPaymentId());

            return (new CreditCard($this))->processSuccessfulPayment($payment);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            return (new CreditCard($this))->processUnsuccessfulPayment($e);
        }
    }

    public function detach(ClientGatewayToken $token)
    {
        $this->init();

        try {
            $this->gateway->mandates->revokeForId($token->gateway_customer_reference, $token->token);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            SystemLogger::dispatch(
                [
                    'server_response' => $e->getMessage(),
                    'data' => request()->all(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_MOLLIE,
                $this->client,
                $this->client->company
            );
        }
    }
}
