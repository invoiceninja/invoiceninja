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

namespace App\PaymentDrivers\Helcim;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\HelcimPaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use Illuminate\Http\Request;

class ACH implements MethodInterface, LivewireMethodInterface
{
    protected HelcimPaymentDriver $helcim_driver;

    public function __construct(HelcimPaymentDriver $helcim_driver)
    {
        $this->helcim_driver = $helcim_driver;
    }

    /**
     * Authorization view for adding a bank account
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->helcim_driver;

        // Initialize HelcimPay.js session for bank account verification (PCI compliant)
        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'verify',
                'currency'    => $this->helcim_driver->client->currency()->code,
                'amount'      => 0,
            ]);

            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token']   = $session['secretToken'];
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            throw new PaymentFailed('Failed to initialize ACH form: ' . $e->getMessage(), 400);
        }

        return render('gateways.helcim.ach.authorize', $data);
    }

    /**
     * Handle authorization response (saving a bank account)
     *
     * PCI COMPLIANCE: Processes tokenized data from HelcimPay.js and performs a
     * server-side verification of the transactionId against the Helcim API to ensure
     * the response has not been tampered with.
     */
    public function authorizeResponse(Request $request)
    {
        $transactionData = $request->input('transaction_data');
        $secretToken     = $request->input('secret_token');

        if (empty($transactionData) || empty($secretToken)) {
            throw new PaymentFailed('Invalid bank account authorization response', 400);
        }

        try {
            $data = json_decode($transactionData, true);

            if (! $data) {
                throw new PaymentFailed('Invalid transaction data format', 400);
            }

            // Check client-side status first (fast-fail)
            if (! isset($data['status']) || $data['status'] !== 'APPROVED') {
                throw new PaymentFailed('Bank account verification failed: ' . ($data['warning'] ?? 'Unknown error'), 400);
            }

            // A transactionId is required for ACH authorization — use it to confirm
            // the verify transaction server-side (tamper-proof check).
            $transactionId = $data['transactionId'] ?? null;
            if (empty($transactionId)) {
                throw new PaymentFailed('No transactionId returned by HelcimPay.js — cannot verify bank account authorization.', 400);
            }

            // SERVER-SIDE TAMPER-PROOF VERIFICATION:
            // Re-fetch the transaction from Helcim using our secret API token.
            $this->helcim_driver->verifyHelcimTransaction(
                $transactionId,
                0.00,
                $this->helcim_driver->client->currency()->code,
                'ach'
            );

            // Extract bank account token
            if (! isset($data['bankToken'])) {
                throw new PaymentFailed('No bank account token received', 400);
            }

            $payment_meta        = new \stdClass();
            $payment_meta->brand = $data['bankAccountType'] ?? 'Bank Account';
            $payment_meta->last4 = $data['bankAccountNumber'] ?? '';
            $payment_meta->type  = GatewayType::BANK_TRANSFER;

            $tokenData = [
                'payment_meta'      => $payment_meta,
                'token'             => $data['bankToken'],
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $this->helcim_driver->storeGatewayToken($tokenData, ['gateway_customer_reference' => $data['bankToken']]);

            SystemLogger::dispatch(
                ['response' => $data, 'data' => $tokenData],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payment_methods.index');
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            throw new PaymentFailed($e->getMessage(), 400);
        }
    }

    /**
     * Payment view for processing an ACH payment
     */
    public function paymentView(array $data)
    {
        $data['gateway']           = $this->helcim_driver;
        $data['amount']            = $this->helcim_driver->payment_hash->data->amount_with_fee;
        $data['currency']          = $this->helcim_driver->client->currency()->code;
        $data['payment_hash']      = $this->helcim_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['tokens']            = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->get();

        // Initialize HelcimPay.js session for new ACH payments (PCI compliant)
        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'purchase',
                'amount'      => $data['amount'],
                'currency'    => $data['currency'],
                'paymentMethod' => 'ach',
            ]);

            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token']   = $session['secretToken'];
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            throw new PaymentFailed('Failed to initialize ACH payment form: ' . $e->getMessage(), 400);
        }

        return render('gateways.helcim.ach.pay', $data);
    }

    /**
     * Process ACH payment response
     *
     * ACH payments via HelcimPay.js are often asynchronous: the HelcimPay.js
     * widget fires a SUCCESS event immediately but the actual bank transfer is
     * processed later. We therefore:
     *  1. Create a PENDING payment record with a temporary placeholder reference.
     *  2. When the Helcim webhook arrives confirming the bank transfer has
     *     cleared, HelcimPaymentDriver::processWebhookRequest() updates the
     *     payment to COMPLETED and sets the real transactionId.
     *
     * For synchronous ACH transactions (where a transactionId is returned
     * immediately), we verify server-side before recording the payment.
     */
    public function paymentResponse(Request $request)
    {
        $paymentHash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        $this->helcim_driver->payment_hash = $paymentHash;
        $this->helcim_driver->init();

        $useToken = $request->input('use_token', false);
        $tokenId  = $request->input('token');

        try {
            if ($useToken && $tokenId) {

                /** @var \App\Models\ClientGatewayToken $token */
                $token = $this->helcim_driver->client->gateway_tokens()
                    ->where('id', $this->helcim_driver->decodePrimaryKey($tokenId))
                    ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
                    ->firstOrFail();

                return $this->processTokenPayment($token, $paymentHash);
            }

            return $this->processHelcimPayACHPayment($request, $paymentHash);
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payment_methods.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Process ACH payment with HelcimPay.js tokenized data.
     *
     * Handles two cases:
     *  a) transactionId is present   → synchronous; verify server-side, record COMPLETED.
     *  b) No transactionId (bankToken only) → async mandate; record PENDING with placeholder.
     */
    private function processHelcimPayACHPayment(Request $request, PaymentHash $paymentHash)
    {
        $transactionData = $request->input('transaction_data');
        $secretToken     = $request->input('secret_token');

        if (empty($transactionData) || empty($secretToken)) {
            throw new PaymentFailed('Invalid ACH payment response', 400);
        }

        $rawData = json_decode($transactionData, true);

        if (! $rawData) {
            throw new PaymentFailed('Invalid transaction data format', 400);
        }

        $amount   = $paymentHash->data->amount_with_fee;
        $currency = $this->helcim_driver->client->currency()->code;

        // Normalize payload keys that differ across HelcimPay.js versions
        $data = $this->normalizeHelcimPayPayload($rawData);

        // ── Case (a): synchronous ACH — transactionId present ────────────────
        if (! empty($data['transactionId'])) {
            // CLIENT-SIDE FAST-FAIL
            if (! isset($data['status']) || ! in_array(strtoupper($data['status']), ['APPROVED', 'PENDING', 'QUEUED', 'SUBMITTED', 'OPENED'], true)) {
                throw new PaymentFailed('ACH payment not approved: ' . ($data['warning'] ?? $data['status'] ?? 'Unknown error'), 400);
            }

            // SERVER-SIDE TAMPER-PROOF VERIFICATION:
            // Re-fetch the transaction from Helcim using our secret API token and assert
            // that the amount, currency and status match what we expect.
            $this->helcim_driver->verifyHelcimTransaction(
                $data['transactionId'],
                (float) $amount,
                $currency,
                'ach'
            );

            $paymentData = [
                'payment_type'          => PaymentType::ACH,
                'amount'                => $amount,
                'transaction_reference' => (string) $data['transactionId'],
                'gateway_type_id'       => GatewayType::BANK_TRANSFER,
            ];

            // ACH payments may remain in a pending state until the bank clears them
            $achStatus = strtoupper($data['status']);
            $paymentStatus = in_array($achStatus, ['APPROVED', 'CLEARED', 'SETTLED', 'COMPLETED', 'SUCCESS'], true)
                ? Payment::STATUS_COMPLETED
                : Payment::STATUS_PENDING;

            $payment = $this->helcim_driver->createPayment($paymentData, $paymentStatus);

            // Store bank token if present
            if (! empty($data['bankToken'])) {
                $this->storeBankToken($data);
            }

            SystemLogger::dispatch(
                ['response' => $data, 'data' => $paymentData],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
        }

        // ── Case (b): asynchronous ACH — no transactionId yet ────────────────
        // HelcimPay.js has confirmed the mandate was initiated but Helcim has not
        // yet assigned a transactionId. Create a PENDING payment with a UUID
        // placeholder reference; the webhook handler will update it once the
        // bank transfer clears.

        $transactionRef = 'ach_pending_' . \Illuminate\Support\Str::uuid();

        $paymentData = [
            'payment_type'          => PaymentType::ACH,
            'amount'                => $amount,
            'transaction_reference' => $transactionRef,
            'gateway_type_id'       => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->helcim_driver->createPayment($paymentData, Payment::STATUS_PENDING);

        // Store bank token if present
        if (! empty($data['bankToken'])) {
            $this->storeBankToken($data);
        }

        SystemLogger::dispatch(
            [
                'info'      => 'ACH payment initiated asynchronously — awaiting webhook confirmation',
                'response'  => $data,
                'data'      => $paymentData,
            ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        // Inform the client that the payment is being processed
        return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)])
            ->with('message', ctrans('texts.payment_pending_ach'));
    }

    /**
     * Process ACH payment with a saved bank token
     */
    private function processTokenPayment(ClientGatewayToken $token, PaymentHash $paymentHash)
    {
        $amount = $paymentHash->data->amount_with_fee;

        $response = $this->helcim_driver->gatewayRequest('/ach/transaction', [
            'bankData'  => ['bankToken' => $token->token],
            'amount'    => $amount,
            'currency'  => $this->helcim_driver->client->currency()->code,
            'ipAddress' => request()->ip(),
            'ecommerce' => true,
        ]);

        $achStatus = strtoupper((string) ($response['status'] ?? ''));

        $successStatuses = ['APPROVED', 'PENDING', 'QUEUED', 'SUBMITTED', 'OPENED', 'CLEARED', 'SETTLED', 'COMPLETED', 'SUCCESS'];

        if (in_array($achStatus, $successStatuses, true)) {
            $data = [
                'payment_type'          => PaymentType::ACH,
                'amount'                => $amount,
                'transaction_reference' => (string) ($response['transactionId'] ?? ''),
                'gateway_type_id'       => GatewayType::BANK_TRANSFER,
            ];

            $paymentStatus = in_array($achStatus, ['APPROVED', 'CLEARED', 'SETTLED', 'COMPLETED', 'SUCCESS'], true)
                ? Payment::STATUS_COMPLETED
                : Payment::STATUS_PENDING;

            $payment = $this->helcim_driver->createPayment($data, $paymentStatus);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
        }

        throw new PaymentFailed($response['message'] ?? 'ACH payment failed', 400);
    }

    /**
     * Normalize HelcimPay.js ACH payload keys across different event versions
     */
    private function normalizeHelcimPayPayload(array $raw): array
    {
        return [
            'transactionId'  => $raw['transactionId']  ?? $raw['transaction_id']  ?? null,
            'status'         => $raw['status']         ?? $raw['transactionStatus'] ?? null,
            'bankToken'      => $raw['bankToken']      ?? $raw['bank_token']       ?? null,
            'bankAccountType'   => $raw['bankAccountType']   ?? $raw['account_type']    ?? null,
            'bankAccountNumber' => $raw['bankAccountNumber'] ?? $raw['account_number']  ?? null,
            'warning'        => $raw['warning']        ?? null,
            // Pass everything else through
        ] + $raw;
    }

    /**
     * Store a bank account token from an ACH response
     */
    private function storeBankToken(array $data): void
    {
        $payment_meta        = new \stdClass();
        $payment_meta->brand = $data['bankAccountType'] ?? 'Bank Account';
        $payment_meta->last4 = $data['bankAccountNumber'] ?? '';
        $payment_meta->type  = GatewayType::BANK_TRANSFER;

        $tokenData = [
            'payment_meta'      => $payment_meta,
            'token'             => $data['bankToken'],
            'payment_method_id' => GatewayType::BANK_TRANSFER,
        ];

        $this->helcim_driver->storeGatewayToken($tokenData, ['gateway_customer_reference' => $data['bankToken']]);
    }

    /**
     * Return the Livewire-compatible blade view path.
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.helcim.ach.pay_livewire';
    }

    /**
     * Prepare payment data for the Livewire/view payment flow.
     */
    public function paymentData(array $data): array
    {
        $this->helcim_driver->payment_hash->data = array_merge((array) $this->helcim_driver->payment_hash->data, $data);
        $this->helcim_driver->payment_hash->save();

        $data['gateway']           = $this->helcim_driver;
        $data['payment_hash']      = $this->helcim_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['amount']            = $this->helcim_driver->payment_hash->data->amount_with_fee; // @phpstan-ignore-line
        $data['currency']          = $this->helcim_driver->client->currency()->code;
        $data['tokens']            = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->get();

        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType'   => 'purchase',
                'amount'        => $data['amount'],
                'currency'      => $data['currency'],
                'paymentMethod' => 'ach',
            ]);
            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token']   = $session['secretToken'];
        } catch (\Exception $e) {
            $data['checkout_token'] = '';
            $data['secret_token']   = '';
        }

        return $data;
    }

    /**
     * Process token billing (recurring ACH payments)
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = $payment_hash->data->amount_with_fee;

        $response = $this->helcim_driver->gatewayRequest('/ach/transaction', [
            'bankData'  => ['bankToken' => $cgt->token],
            'amount'    => $amount,
            'currency'  => $this->helcim_driver->client->currency()->code,
            'ipAddress' => request()->ip(),
            'ecommerce' => true,
        ]);

        $achStatus = strtoupper((string) ($response['status'] ?? ''));

        $successStatuses = ['APPROVED', 'PENDING', 'QUEUED', 'SUBMITTED', 'OPENED', 'CLEARED', 'SETTLED', 'COMPLETED', 'SUCCESS'];

        if (in_array($achStatus, $successStatuses, true)) {
            $data = [
                'payment_type'          => PaymentType::ACH,
                'amount'                => $amount,
                'transaction_reference' => (string) ($response['transactionId'] ?? ''),
                'gateway_type_id'       => GatewayType::BANK_TRANSFER,
            ];

            $paymentStatus = in_array($achStatus, ['APPROVED', 'CLEARED', 'SETTLED', 'COMPLETED', 'SUCCESS'], true)
                ? Payment::STATUS_COMPLETED
                : Payment::STATUS_PENDING;

            $payment = $this->helcim_driver->createPayment($data, $paymentStatus);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return $payment;
        }

        SystemLogger::dispatch(
            ['error' => $response['message'] ?? 'ACH token billing failed', 'response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        throw new PaymentFailed($response['message'] ?? 'ACH payment failed', 400);
    }
}