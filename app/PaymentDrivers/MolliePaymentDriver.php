<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Gateways\Mollie\Mollie3dsRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\Client;
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
use App\PaymentDrivers\Mollie\IDEAL;
use App\PaymentDrivers\Mollie\KBC;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;

class MolliePaymentDriver extends BaseDriver
{
    use MakesHash;

    /**
     * @var bool
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
        GatewayType::IDEAL => IDEAL::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_MOLLIE;

    /**
     * Initialize the Mollie API client with the API key from the company gateway.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function init(): self
    {
        $this->gateway = new MollieApiClient();

        $this->gateway->setApiKey(
            $this->company_gateway->getConfigField('apiKey'),
        );

        return $this;
    }

    /**
     * Get the list of supported gateway types for Mollie.
     *
     * @return array Array of supported gateway type constants.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANCONTACT;
        $types[] = GatewayType::BANK_TRANSFER;
        $types[] = GatewayType::KBC;
        $types[] = GatewayType::IDEAL;

        return $types;
    }

    /**
     * Set the payment method for the current transaction.
     *
     * @param int $payment_method_id The payment method identifier
     * @return self Returns the current instance for method chaining.
     */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Show the authorization page for the payment method.
     *
     * @param array $data Payment method data
     * @return mixed The response from the payment method's authorizeView method
     */
    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    /**
     * Handle the authorization response from the payment gateway.
     *
     * @param mixed $request The authorization request
     * @return mixed The response from the payment method's authorizeResponse method
     */
    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    /**
     * Show the payment page for the payment method.
     *
     * @param array $data Payment data
     * @return mixed The response from the payment method's paymentView method
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Handle the payment response from the payment gateway.
     *
     * @param mixed $request The payment response request
     * @return mixed The response from the payment method's paymentResponse method
     * @throws \Exception When the payment processing fails
     */
    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        try {
            $molliePayment = $this->gateway->payments->get($payment->transaction_reference);

            $refund = $this->gateway->payments->refund($molliePayment, [
                'amount' => [
                    'currency' => $this->client->currency()->code,
                    'value' => $this->convertToMollieAmount((float) $amount),
                ],
            ]);

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
                'success' => true,
                'description' => $refund->description,
                'code' => 0,
            ];
        } catch (ApiException $e) {
            SystemLogger::dispatch(
                ['server_response' => $e->getMessage(), 'data' => request()->all()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_MOLLIE,
                $this->client,
                $this->client->company
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

    /**
     * Process a payment using a stored payment token.
     *
     * @param ClientGatewayToken $cgt The client gateway token containing payment method details
     * @param PaymentHash $payment_hash The payment hash containing payment details
     * @return Payment|null The created payment or null if payment fails
     * @throws \Exception When there's an error processing the payment
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash): ?Payment
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

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
            $molliePayment = $this->gateway->payments->create([
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
        } catch (ApiException $e) {
            $this->unWindGatewayFees($payment_hash);

            $this->sendFailureMail("Could not create payment in Mollie: ".$e->getPlainMessage());

            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => $e->getCode(),
                'param' => '',
                'message' => $e->getMessage(),
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_MOLLIE, $this->client, $this->client->company);
            return null;
        }

        try {
            $data = [
                'payment_method' => $cgt->token,
                'payment_type' => self::convertFromMolliePaymentType($molliePayment->method),
                'amount' => $amount,
                'transaction_reference' => $molliePayment->id,
                'gateway_type_id' => self::convertFromMollieGatewayType($molliePayment->method),
            ];

            $this->confirmGatewayFee($data);

            $payment = $this->createPayment($data, self::convertFromMollieStatus($molliePayment->status));

            SystemLogger::dispatch(
                ['response' => $payment, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_MOLLIE,
                $this->client,
                $this->client->company
            );
            return $payment;
        } catch (\Exception $e) {
            // If we reach this, there is a programming error
            // As we're not dealing with third-party API's in try { block
            $this->unWindGatewayFees($payment_hash);

            $message = [
                'server_response' => $molliePayment,
                'data' => $payment_hash->data,
                'exception' => $e->getMessage()
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_LOG,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_FAILURE,
                $this->client,
                $this->client->company
            );
            throw $e; // Rethrow because it is an programming error.
        }
        return null;
    }

    /**
     * Process an incoming webhook request from Mollie.
     *
     * @param PaymentWebhookRequest $request The webhook request
     * @return JsonResponse JSON response indicating success or failure
     * @throws \Exception When there's an error processing the webhook
     */
    public function processWebhookRequest(PaymentWebhookRequest $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'string', 'starts_with:tr', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        nlog("Mollie webhook called with id: {$request->id}", $request->toArray());

        $this->init();
        try {
            $molliePayment = $this->gateway->payments->get($request->id);

            $payment = Payment::withTrashed()->where('transaction_reference', $request->id)->first();

            if (!$payment) {
                // Construct the payment record from the metadata in the payment hash.
                if (!$molliePayment->metadata?->client_id) {
                    throw new \Exception('No client_id found in Mollie payment metadata');
                }
                $client = Client::withTrashed()->findOrFail($this->decodePrimaryKey($molliePayment->metadata->client_id));
                $this->client = $client;

                if (!$molliePayment->metadata?->hash) {
                    throw new \Exception('No payment hash found in Mollie payment metadata');
                }
                $payment_hash = PaymentHash::where('hash', $molliePayment->metadata->hash)->firstOrFail();
                // If we are here, then we do not have access to the class payment hash, so lets set it here
                $this->payment_hash = $payment_hash;

                $data = [
                    'gateway_type_id' => $molliePayment->metadata->gateway_type_id,
                    'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total,
                    'payment_type' => $molliePayment->metadata->payment_type_id,
                    'transaction_reference' => $molliePayment->id,
                    'idempotency_key' => substr("{$molliePayment->id}{$payment_hash->hash}", 0, 64)
                ];

                // Uses $this->payment_hash
                $this->confirmGatewayFee($data);

                // Uses $this->payment_hash
                $payment = $this->createPayment(
                    $data,
                    self::convertFromMollieStatus($molliePayment->status)
                );
            } else {
                $client = $payment->client;
                $this->client = $client;
            }

            $this->createClientGatewayTokenFromMolliePayment($molliePayment);

            $status = self::convertFromMollieStatus($molliePayment->status);
            if (in_array($status, [Payment::STATUS_CANCELLED, Payment::STATUS_FAILED])) {
                if ($molliePayment->metadata?->hash) {
                    $payment_hash = PaymentHash::where('hash', $molliePayment->metadata->hash)->firstOrFail();
                    $this->handlePendingGatewayFeeRemoval($payment_hash);
                    $this->payment_hash = $payment_hash; // Used by sendFailureMail()
                }
                if (!in_array($payment->status_id, [Payment::STATUS_CANCELLED, Payment::STATUS_FAILED])) {
                    // Payment was moved from other status into CANCELLED or FAILED
                    $this->sendFailureMail($molliePayment->details?->failureMessage ?? "There was a problem processing your payment.");
                }
                // Sets payment status to cancelled synchronously and handles other consequences
                $payment->service()->deletePayment(false);
            }
            if ($payment->status_id !== Payment::STATUS_COMPLETED && $status === Payment::STATUS_COMPLETED){
                // Payment was moved from other status into COMPLETED status
                if ($this->client->getSetting('client_online_payment_notification')) {
                    $payment->service()->sendEmail();
                }
            }

            $payment->status_id = $status; // Set or overwrite payment status to the mollie status
            $payment->date = $molliePayment->paidAt ?: null;

            // Handle refunded amounts
            if ($molliePayment->amountRefunded?->currency === $payment->currency->code) {
                $payment->refunded = self::convertFromMollieAmount($molliePayment->amountRefunded->value);
            }

            // Handle remaining amount (applied amount)
            if ($molliePayment->amountRemaining?->currency === $payment->currency->code) {
                $payment->applied = self::convertFromMollieAmount($molliePayment->amountRemaining->value);
            } else {
                // If no remaining amount, use the full amount as applied for completed payments
                if ($molliePayment->isPaid() || $molliePayment->isAuthorized()) {
                    $payment->applied = $payment->amount - ($payment->refunded ?? 0);
                }
            }

            // Add description to private notes if not already present
            $private_notes = "description: " . $molliePayment->description;
            if (!str_contains($payment->private_notes ?? "", $private_notes)) {
                $payment->private_notes .= $private_notes;
            }

            $payment->save();

            SystemLogger::dispatch([
                    'request' => $request->toArray(),
                    'molliePayment' => $molliePayment,
                    'payment' => $payment
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_MOLLIE,
                $this->client,
                $this->company_gateway->company
            );

            return response()->json([], 200);
        } catch (\Exception $e) {
            $ctx = [
                'request' => $request->toArray(),
                'exception' => $e->getMessage()
            ];
            nlog("Mollie webhook call failed", $ctx);
            SystemLogger::dispatch($ctx,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_MOLLIE,
                $this->client,
                $this->company_gateway->company
            );
        }
        return response()->json([], 500);
    }

    /**
     * Stores a Mollie mandateId as ClientGatewayToken based on the mollie customerId attached to a given Mollie Payment.
     * @param \Mollie\Api\Resources\Payment $payment
     * @return ClientGatewayToken|null Returns new ClientGatewayToken on success, null on failure or if token already exists
     * @throws ApiException
     */
    public function createClientGatewayTokenFromMolliePayment(\Mollie\Api\Resources\Payment $payment): ?ClientGatewayToken
    {
        if (!in_array($payment->status, ['paid'])) {
            return null;
        }

        if (!$payment->metadata?->hash) {
            return null;
        }

        $payment_hash = PaymentHash::where('hash', $payment->metadata->hash)->first();

        if (!$payment_hash || !property_exists($payment_hash->data, 'shouldStoreToken') || !$payment_hash->data->shouldStoreToken) {
            return null;
        }

        $mandates = \iterator_to_array($this->gateway->mandates->listForId($payment_hash->data->mollieCustomerId));
        $mandate = !empty($mandates) ? $mandates[0] : null;

        if (!$mandate) {
            return null;
        }

        $token_already_exists = $this->client->gateway_tokens
            ->where('token', $mandate->id)
            ->where('company_gateway_id', $this->company_gateway->id)
            ->first();

        if ($token_already_exists) {
            return null;
        }

        $payment_method_id = self::convertFromMollieGatewayType($mandate->method);
        $payment_meta = new \stdClass();
        $payment_meta->type = $payment_method_id;

        if ($payment_method_id == GatewayType::CREDIT_CARD) {
            // Parse the card expiry date (format: YYYY-MM-DD)
            $dateParts = explode('-', $mandate->details->cardExpiryDate);
            if (count($dateParts) >= 2) {
                $payment_meta->exp_year = substr($dateParts[0], -2); // Last 2 digits of YYYY
                $payment_meta->exp_month = ltrim($dateParts[1], '0'); // MM (remove leading zero)
            }
            $payment_meta->brand = $mandate->details->cardLabel;
            $payment_meta->last4 = $mandate->details->cardNumber;
        } elseif ($payment_method_id == GatewayType::DIRECT_DEBIT) {
            $payment_meta->last4 = substr($mandate->details->consumerAccount, -4); // Last 4 characters
            $payment_meta->brand = "mollie";
        }

        return $this->storeGatewayToken([
            'token' => $mandate->id,
            'payment_method_id' => $payment_method_id,
            'payment_meta' => $payment_meta,
        ], ['gateway_customer_reference' => $payment_hash->data->mollieCustomerId]);
    }

    /**
     * Remove pending gateway fees from an invoice.
     *
     * @param PaymentHash $payment_hash The payment hash containing fee information
     * @return void
     */
    private function handlePendingGatewayFeeRemoval(PaymentHash $payment_hash)
    {
        $invoice = $payment_hash->fee_invoice;

        if($invoice){
            $line_items = $invoice->line_items;

            $line_items = collect($line_items)->filter(function($line_item, $key) use ($line_items) {
                if ($key === array_key_last($line_items)) {
                    return $line_item->type_id != '4';
                }
                return true;
            })->toArray();

            $invoice->line_items = array_values($line_items);
            $invoice = $invoice->calc()->getInvoice();
        }
    }

    /**
     * Process 3D Secure confirmation for a payment.
     *
     * @param Mollie3dsRequest $request The 3DS confirmation request
     * @return mixed The result of the payment processing
     */
    public function process3dsConfirmation(Mollie3dsRequest $request)
    {
        $this->init();
        $this->setPaymentHash($request->getPaymentHash());

        try {
            $payment = $this->gateway->payments->get($request->getPaymentId());
            // if($payment->status == 'open'){
            //     nlog("open furfy");
            //     return render('gateways.mollie.mollie_pending_payment_placeholder');
            // }
            // else

            if($payment->status == 'failed'){
                return (new CreditCard($this))->processUnsuccessfulPayment($payment->details->failureMessage);
            }

            return (new CreditCard($this))->processSuccessfulPayment($payment);
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            return (new CreditCard($this))->processUnsuccessfulPayment($e);
        }
    }

    /**
     * Detach a payment method by revoking the mandate from Mollie.
     *
     * @param ClientGatewayToken $token The client gateway token to detach
     * @return void
     */
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

    /**
     * Convert a Mollie amount string back to a float.
     *
     * @param string $amount The amount string from Mollie (e.g., "123.45")
     * @return float The converted amount as a float
     * @throws \InvalidArgumentException If the input is not a valid numeric string
     */
    public static function convertFromMollieAmount(string $amount): float
    {
        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException("Invalid amount format. Expected a numeric string, got: " . $amount);
        }

        return (float) $amount;
    }

    /**
     * Convert ISO 4217 currency code to InvoiceNinja currency ID
     *
     * @param string $currencyCode ISO 4217 currency code (e.g., 'EUR', 'USD')
     * @return int Returns the currency ID
     * @throws ModelNotFoundException When the currency is not found
     */
    public static function convertFromMollieCurrency(string $currencyCode): int
    {
        $currency = \App\Models\Currency::where('code', strtoupper($currencyCode))->firstOrFail();
        return $currency->id;
    }

    /**
     * Convert Mollie payment method to PaymentType ID
     *
     * @param string $type
     * @return int
     * @throws \Exception When the payment method is not supported
     */
    static public function convertFromMolliePaymentType(string $type): int
    {
        $types = [
            'banktransfer' => PaymentType::BANK_TRANSFER,
            'creditcard' => PaymentType::CREDIT_CARD_OTHER,
            'directdebit' => PaymentType::DIRECT_DEBIT,
            'ideal' => PaymentType::IDEAL,
            'bancontact' => PaymentType::BANCONTACT,
            'sofort' => PaymentType::SOFORT,
            'klarnapaylater' => PaymentType::KLARNA,
            'klarnasliceit' => PaymentType::KLARNA,
            'klarnapaynow' => PaymentType::KLARNA,
            'kbc' => PaymentType::KBC,
            'eps' => PaymentType::EPS,
            'giropay' => PaymentType::GIROPAY,
            'p24' => PaymentType::PRZELEWY24,
            'applepay' => PaymentType::CREDIT_CARD_OTHER,
            'paypal' => PaymentType::PAYPAL,
            'belfius' => PaymentType::BANK_TRANSFER,
            'inghomepay' => PaymentType::BANK_TRANSFER,
            'giftcard' => PaymentType::CREDIT,
            'paysafecard' => PaymentType::CREDIT,
            'przelewy24' => PaymentType::PRZELEWY24,
            'mybank' => PaymentType::BANK_TRANSFER,
            'billet' => PaymentType::BANK_TRANSFER,
            'tikkiepayment' => PaymentType::BANK_TRANSFER,
        ];

        if (!array_key_exists($type, $types)) {
            throw new \Exception("Unsupported Mollie payment method: " . $type);
        }

        return $types[$type];
    }

    /**
     * Convert Mollie payment method to GatewayType ID
     *
     * @param string $type
     * @return int
     * @throws \Exception When the payment method is not supported
     */
    static public function convertFromMollieGatewayType(string $type): int
    {
        $types = [
            'creditcard' => GatewayType::CREDIT_CARD,
            'directdebit' => GatewayType::DIRECT_DEBIT,
            'paypal' => GatewayType::PAYPAL,
            'bancontact' => GatewayType::BANCONTACT,
            'banktransfer' => GatewayType::BANK_TRANSFER,
            'kbc' => GatewayType::KBC,
            'ideal' => GatewayType::IDEAL,
        ];

        if (!isset($types[strtolower($type)])) {
            throw new \Exception("Unsupported Mollie payment method: " . $type);
        }

        return $types[strtolower($type)];
    }

    /**
     * Convert Mollie payment status to InvoiceNinja payment status
     *
     * @param string $mollieStatus
     * @return int
     */
    static public function convertFromMollieStatus(string $mollieStatus): int
    {
        $statusMap = [
            'paid' => Payment::STATUS_COMPLETED,
            'authorized' => Payment::STATUS_PENDING,
            'pending' => Payment::STATUS_PENDING,
            'failed' => Payment::STATUS_FAILED,
            'expired' => Payment::STATUS_FAILED,
            'canceled' => Payment::STATUS_CANCELLED,
            'refunded' => Payment::STATUS_REFUNDED,
            'partially_refunded' => Payment::STATUS_PARTIALLY_REFUNDED,
        ];

        return $statusMap[strtolower($mollieStatus)] ?? Payment::STATUS_FAILED;
    }

    /**
     * Test the connection to the Mollie API.
     *
     * @return string 'ok' if the connection is successful, 'error' otherwise
     */
    public function auth(): string
    {
        $this->init();

        try {
            // Attempt to fetch a page of payments to test the connection
            $p = $this->gateway->payments->page();
            return 'ok';
        } catch (\Exception $e) {
            // Log the error or handle it as needed
            return 'error';
        }
    }
}
