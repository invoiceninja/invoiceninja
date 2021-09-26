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

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Gateways\Mollie\Mollie3dsRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Mollie\Bancontact;
use App\PaymentDrivers\Mollie\BankTransfer;
use App\PaymentDrivers\Mollie\CreditCard;
use App\PaymentDrivers\Mollie\KBC;
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
        GatewayType::BANCONTACT => Bancontact::class,
        GatewayType::BANK_TRANSFER => BankTransfer::class,
        GatewayType::KBC => KBC::class,
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
        $types[] = GatewayType::BANCONTACT;
        $types[] = GatewayType::BANK_TRANSFER;
        $types[] = GatewayType::KBC;

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
        $this->init();

        try {
            $payment = $this->gateway->payments->get($payment->transaction_reference);

            $refund = $this->gateway->payments->refund($payment, [
                'amount' => [
                    'currency' => $this->client->currency()->code,
                    'value' => $this->convertToMollieAmount((float) $amount),
                ],
            ]);

            if ($refund->status === 'refunded') {
                SystemLogger::dispatch(
                    ['server_response' => $refund, 'data' => request()->all()],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_MOLLIE,
                    $this->client,
                    $this->client->company
                );

                return [
                    'transaction_reference' => $refund->id,
                    'transaction_response' => json_encode($refund),
                    'success' => $refund->status === 'refunded' ? true : false,
                    'description' => $refund->description,
                    'code' => 200,
                ];
            }

            return [
                'transaction_reference' => $refund->id,
                'transaction_response' => json_encode($refund),
                'success' => true,
                'description' => $refund->description,
                'code' => 0,
            ];
        } catch (ApiException $e) {
            SystemLogger::dispatch(
                ['server_response' => $refund, 'data' => request()->all()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_MOLLIE,
                $this->client,
                $this->client->companyk
            );

            nlog($e->getMessage());

            return [
                'transaction_reference' => null,
                'transaction_response' => $e->getMessage(),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->client->present()->name()}";
        }

        $request = new PaymentResponseRequest();
        $request->setMethod('POST');
        $request->request->add(['payment_hash' => $payment_hash->hash]);

        $this->init();

        try {
            $payment = $this->gateway->payments->create([
                'amount' => [
                    'currency' => $this->client->currency()->code,
                    'value' => $this->convertToMollieAmount($amount),
                ],
                'mandateId' => $cgt->token,
                'customerId' => $cgt->gateway_customer_reference,
                'sequenceType' => 'recurring',
                'description' => $description,
                'webhookUrl'  => $this->company_gateway->webhookUrl(),
            ]);

            if ($payment->status === 'paid') {
                $this->confirmGatewayFee($request);

                $data = [
                    'payment_method' => $cgt->token,
                    'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                    'amount' => $amount,
                    'transaction_reference' => $payment->id,
                    'gateway_type_id' => GatewayType::CREDIT_CARD,
                ];

                $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

                SystemLogger::dispatch(
                    ['response' => $payment, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_MOLLIE,
                    $this->client
                );

                return $payment;
            }

            $this->unWindGatewayFees($payment_hash);

            PaymentFailureMailer::dispatch(
                $this->client,
                $payment->details,
                $this->client->company,
                $amount
            );

            $message = [
                'server_response' => $payment,
                'data' => $payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $this->client
            );

            return false;
        } catch (ApiException $e) {
            $this->unWindGatewayFees($payment_hash);

            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => $e->getCode(),
                'param' => '',
                'message' => $e->getMessage(),
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_MOLLIE, $this->client, $this->client->company);
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        // Allow app to catch up with webhook request.
        sleep(2);

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
        } catch (ApiException $e) {
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

    /**
     * Convert the amount to the format that Mollie supports.
     * 
     * @param mixed|float $amount 
     * @return string 
     */
    public function convertToMollieAmount($amount): string
    {
        return \number_format((float) $amount, 2, '.', '');
    }
}
