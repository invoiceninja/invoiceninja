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
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\HelcimPaymentDriver;
use Illuminate\Http\Request;

class ACH implements MethodInterface, LivewireMethodInterface
{
    protected HelcimPaymentDriver $helcim_driver;

    public function __construct(HelcimPaymentDriver $helcim_driver)
    {
        $this->helcim_driver = $helcim_driver;
    }

    /**
     * Authorization view for adding a bank account via HelcimPay.js
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->helcim_driver;

        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'verify',
                'currency' => $this->helcim_driver->client->currency()->code,
                'amount' => 0,
                'paymentMethod' => 'ach',
            ]);

            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token'] = $session['secretToken'];
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                ['error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            throw new PaymentFailed('Failed to initialize bank account form: ' . $e->getMessage(), 400);
        }

        return render('gateways.helcim.ach.authorize', $data);
    }

    /**
     * Handle authorization response — save the bank account token from HelcimPay.js
     */
    public function authorizeResponse(Request $request)
    {
        $transactionData = $request->input('transaction_data');
        $transactionHash = $request->input('transaction_hash') ?? '';
        $secretToken = $request->input('secret_token');

        if (empty($transactionData) || empty($secretToken)) {
            throw new PaymentFailed('Invalid bank account response', 400);
        }

        try {
            $rawData = json_decode($transactionData, true);

            if (!$rawData) {
                throw new PaymentFailed('Invalid transaction data format', 400);
            }

            $data = $this->normalizeHelcimPayPayload($rawData);

            if (!$this->helcim_driver->validateHelcimPayResponse($transactionData, $transactionHash, $secretToken)) {
                throw new PaymentFailed('Transaction validation failed - data may have been tampered with', 400);
            }

            if (!$this->isApprovedAchResponse($data)) {
                throw new PaymentFailed('Bank account verification failed: ' . $this->extractFailureReason($data), 400);
            }

            $transactionId = $this->extractValue($data, ['transactionId', 'transaction.id', 'id', 'transactionId.id', 'verification.transactionId']);
            $bankAccountId = $this->extractValue($data, ['bankAccountId', 'bankAccount.id', 'bank.id', 'paymentMethod.id', 'bankAccountId.id', 'account.id']);
            $customerId = $this->extractValue($data, ['customerId', 'customer.id', 'customer.customerId', 'account.customerId']);
            $bankToken = $this->extractValue($data, ['bankToken', 'token', 'paymentMethod.token', 'bank.token', 'account.token', 'achToken']);
            $customerCode = $this->extractValue($data, ['customerCode', 'customer.code', 'customer.customerCode']);

            // Resolve bankAccountId/customerId via API if absent (typical for ACH verify payloads).
            // These are required for recurring/token billing via PUT /ach/withdraw.
            if (!$bankAccountId || !$customerId) {
                [$resolvedBankAccountId, $resolvedCustomerId] = $this->resolveAchBankAccountDetails($customerCode, $bankToken);
                $bankAccountId = $bankAccountId ?: $resolvedBankAccountId;
                $customerId = $customerId ?: $resolvedCustomerId;
            }

            $tokenReference = (string) ($bankToken ?: $bankAccountId ?: $transactionId ?: '');

            if ($tokenReference === '') {
                SystemLogger::dispatch(
                    [
                        'error' => 'No bank account reference received from Helcim verification',
                        'diagnostics' => $this->buildAchDiagnostics($rawData, $data),
                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_HELCIM,
                    $this->helcim_driver->client,
                    $this->helcim_driver->client->company
                );

                throw new PaymentFailed('No bank account reference received from Helcim verification', 400);
            }

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = null;
            $payment_meta->exp_year = null;
            $payment_meta->brand = 'ACH';
            $accountNumber = (string) ($this->extractValue($data, ['bankAccountNumber', 'bankAccount.number', 'maskedAccountNumber', 'cardNumber']) ?? '');
            $payment_meta->last4 = $accountNumber !== '' ? substr($accountNumber, -4) : '';
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->customerCode = $customerCode;
            // Store bankAccountId and customerId if provided for future token billing
            $payment_meta->bankAccountId = $bankAccountId;
            $payment_meta->customerId = $customerId;
            $payment_meta->bankToken = $bankToken;
            $payment_meta->transactionId = $transactionId;

            $tokenData = [
                'payment_meta' => $payment_meta,
                'token' => $tokenReference,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $this->helcim_driver->storeGatewayToken($tokenData, [
                'gateway_customer_reference' => (string) ($customerCode ?: $customerId ?: $transactionId ?: $tokenReference),
            ]);

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
                [
                    'error' => $e->getMessage(),
                    'diagnostics' => isset($rawData) && is_array($rawData)
                        ? $this->buildAchDiagnostics($rawData, $data ?? [])
                        : ['has_transaction_data' => !empty($transactionData)],
                ],
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
     * Payment view for ACH — uses HelcimPay.js with paymentMethod: 'ach'
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->helcim_driver;
        $data['amount'] = $this->helcim_driver->payment_hash->data->amount_with_fee;
        $data['currency'] = $this->helcim_driver->client->currency()->code;
        $data['payment_hash'] = $this->helcim_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['tokens'] = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->get();

        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'purchase',
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'paymentMethod' => 'ach',
            ]);

            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token'] = $session['secretToken'];
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
     * Process ACH payment response from HelcimPay.js or saved token
     */
    public function paymentResponse(Request $request)
    {
        $paymentHash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        $this->helcim_driver->payment_hash = $paymentHash;
        $this->helcim_driver->init();

        $useToken = $request->input('use_token', false);
        $tokenId = $request->input('token');

        try {
            if ($useToken && $tokenId) {
                $token = $this->helcim_driver->client->gateway_tokens()
                    ->where('id', $this->helcim_driver->decodePrimaryKey($tokenId))
                    ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
                    ->firstOrFail();

                return $this->processTokenPayment($token, $paymentHash);
            }

            return $this->processHelcimPayAchPayment($request, $paymentHash);
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
     * Process ACH payment via HelcimPay.js response
     * ACH payments start as PENDING — settlement is asynchronous
     */
    private function processHelcimPayAchPayment(Request $request, PaymentHash $paymentHash)
    {
        $transactionData = $request->input('transaction_data');
        $transactionHash = $request->input('transaction_hash') ?? '';
        $secretToken = $request->input('secret_token');
        $storeAccount = $request->input('store_card', false);

        if (empty($transactionData) || empty($secretToken)) {
            throw new PaymentFailed('Invalid ACH payment response', 400);
        }

        $rawData = json_decode($transactionData, true);

        if (!$rawData) {
            throw new PaymentFailed('Invalid transaction data format', 400);
        }

        $data = $this->normalizeHelcimPayPayload($rawData);

        if (!$this->helcim_driver->validateHelcimPayResponse($transactionData, $transactionHash, $secretToken)) {
            throw new PaymentFailed('Transaction validation failed - data may have been tampered with', 400);
        }

        if (!$this->isApprovedAchResponse($data)) {
            throw new PaymentFailed('ACH payment failed: ' . $this->extractFailureReason($data), 400);
        }

        $amount = $paymentHash->data->amount_with_fee;

        $transactionRef = (string) ($data['transactionId'] ?? '');

        // ACH mandates may be processed asynchronously — HelcimPay.js may fire SUCCESS
        // before Helcim assigns a transactionId. Generate a placeholder reference so the
        // payment is recorded as PENDING immediately; the webhook will update the status
        // once Helcim settles (mirrors how Stripe handles pending ACH payments).
        if ($transactionRef === '') {
            $transactionRef = 'ach_pending_' . \Illuminate\Support\Str::uuid();

            SystemLogger::dispatch(
                [
                    'warning' => 'ACH payment approved but no transactionId returned by HelcimPay.js — mandate is being processed asynchronously. Created PENDING payment with placeholder reference.',
                    'placeholder_reference' => $transactionRef,
                    'response_keys' => array_keys($data),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );
        }

        $paymentData = [
            'payment_type' => PaymentType::ACH,
            'amount' => $amount,
            'transaction_reference' => $transactionRef,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        // ACH payments are asynchronous — mark as pending until settlement confirmed
        $payment = $this->helcim_driver->createPayment($paymentData, Payment::STATUS_PENDING);

        // Store bank account for future use if requested
        if ($storeAccount) {
            $transactionId = $this->extractValue($data, ['transactionId', 'transaction.id', 'id', 'transactionId.id', 'verification.transactionId']);
            $bankAccountId = $this->extractValue($data, ['bankAccountId', 'bankAccount.id', 'bank.id', 'paymentMethod.id', 'bankAccountId.id', 'account.id']);
            $customerId = $this->extractValue($data, ['customerId', 'customer.id', 'customer.customerId', 'account.customerId']);
            $bankToken = $this->extractValue($data, ['bankToken', 'token', 'paymentMethod.token', 'bank.token', 'account.token', 'achToken']);
            $customerCode = $this->extractValue($data, ['customerCode', 'customer.code', 'customer.customerCode']);
            $tokenReference = (string) ($bankToken ?: $bankAccountId ?: $transactionId ?: '');

            if ($tokenReference === '') {
                SystemLogger::dispatch(
                    ['warning' => 'Unable to save ACH token, missing token reference', 'response' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_HELCIM,
                    $this->helcim_driver->client,
                    $this->helcim_driver->client->company
                );

                return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
            }

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = null;
            $payment_meta->exp_year = null;
            $payment_meta->brand = 'ACH';
            $accountNumber = (string) ($this->extractValue($data, ['bankAccountNumber', 'bankAccount.number', 'maskedAccountNumber', 'cardNumber']) ?? '');
            $payment_meta->last4 = $accountNumber !== '' ? substr($accountNumber, -4) : '';
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->customerCode = $customerCode;
            $payment_meta->bankAccountId = $bankAccountId;
            $payment_meta->customerId = $customerId;
            $payment_meta->bankToken = $bankToken;
            $payment_meta->transactionId = $transactionId;

            $tokenData = [
                'payment_meta' => $payment_meta,
                'token' => $tokenReference,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $this->helcim_driver->storeGatewayToken($tokenData, [
                'gateway_customer_reference' => (string) ($customerCode ?: $customerId ?: $transactionId ?: $tokenReference),
            ]);
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

    /**
     * Process ACH payment using a saved bank account token
     * Uses PUT /ach/withdraw — requires bankAccountId and customerId stored in token meta
     */
    private function processTokenPayment(ClientGatewayToken $token, PaymentHash $paymentHash)
    {
        $payment = $this->tokenBilling($token, $paymentHash);

        return redirect()->route('client.payments.show', ['payment' => $this->helcim_driver->encodePrimaryKey($payment->id)]);
    }

    /**
     * Return the Livewire-compatible blade view path.
     * Called by BaseDriver::livewirePaymentView().
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.helcim.ach.pay_livewire';
    }

    /**
     * Prepare payment data for the Livewire/view payment flow.
     * Called by BaseDriver::processPaymentViewData().
     * Must include ALL variables the pay_livewire.blade.php view needs.
     */
    public function paymentData(array $data): array
    {
        $this->helcim_driver->payment_hash->data = array_merge((array) $this->helcim_driver->payment_hash->data, $data);
        $this->helcim_driver->payment_hash->save();

        $data['gateway'] = $this->helcim_driver;
        $data['payment_hash'] = $this->helcim_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['amount'] = $this->helcim_driver->payment_hash->data->amount_with_fee;
        $data['currency'] = $this->helcim_driver->client->currency()->code;
        $data['tokens'] = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->get();

        try {
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'purchase',
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'paymentMethod' => 'ach',
            ]);
            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token'] = $session['secretToken'];
        } catch (\Exception $e) {
            $data['checkout_token'] = '';
            $data['secret_token'] = '';
        }

        return $data;
    }

    /**
     * HelcimPay.js ACH responses: treat any response that has a transactionId as success,
     * since HelcimPay.js only fires eventStatus=SUCCESS for completed transactions.
     * ACH uses statusAuth (PENDING/APPROVED) and statusClearing (OPENED/CLEARED) instead
     * of the card-style status field.
     */
    private function isApprovedAchResponse(array $data): bool
    {
        $paymentType = strtoupper((string) ($this->extractValue($data, ['paymentType', 'data.paymentType']) ?? ''));
        $hasBankToken = !empty($this->extractValue($data, [
            'bankToken',
            'token',
            'paymentMethod.token',
            'bank.token',
            'account.token',
            'achToken',
        ]));
        $hasMaskedAccount = !empty($this->extractValue($data, [
            'bankAccountNumber',
            'bankAccount.number',
            'maskedAccountNumber',
        ]));

        $statusCandidates = [
            $data['status'] ?? null,
            $data['statusAuth'] ?? null,
            $data['statusClearing'] ?? null,
            $data['eventStatus'] ?? null,
            data_get($data, 'transaction.status'),
            data_get($data, 'transaction.statusAuth'),
            data_get($data, 'transaction.statusClearing'),
            data_get($data, 'data.status'),
            data_get($data, 'data.statusAuth'),
            data_get($data, 'data.statusClearing'),
        ];

        $normalizedStatuses = array_values(array_filter(array_map(static function ($status) {
            if ($status === null || $status === '') {
                return null;
            }

            return strtoupper((string) $status);
        }, $statusCandidates)));

        $explicitFailureStatuses = ['DECLINED', 'FAILED', 'ERROR', 'REJECTED', 'VOIDED', 'CANCELLED'];

        foreach ($normalizedStatuses as $status) {
            if (in_array($status, $explicitFailureStatuses, true)) {
                return false;
            }
        }

        // ACH verify/tokenization responses may not include transaction/status fields.
        // Accept verify success when Helcim returns a token (or masked account) and
        // no explicit failure status is present.
        if ($paymentType === 'VERIFY' && ($hasBankToken || $hasMaskedAccount)) {
            return true;
        }

        // Some ACH payload variants may omit paymentType but still include only tokenized
        // account details (without transactionId/status). Accept if token exists unless
        // explicitly failed above.
        if ($hasBankToken) {
            return true;
        }

        $transactionId = $this->extractValue($data, [
            'transactionId',
            'transaction.id',
            'id',
            'data.transactionId',
            'data.transaction.id',
            'data.id',
        ]);

        // If HelcimPay.js returned a transaction id, accept unless explicitly declined
        if (!empty($transactionId)) {
            return true;
        }

        if (in_array('SUCCESS', $normalizedStatuses, true)) {
            return true;
        }

        $approvedStatuses = ['APPROVED', 'PENDING', 'QUEUED', 'SUBMITTED', 'OPENED', 'CLEARED'];

        foreach ($normalizedStatuses as $status) {
            if (in_array($status, $approvedStatuses, true)) {
                return true;
            }
        }

        return false;
    }

    private function extractFailureReason(array $data): string
    {
        $reason = $this->extractValue($data, [
            'warning',
            'message',
            'error',
            'responseMessage',
            'statusMessage',
            'errors.0.message',
            'errors.0.error',
            'data.warning',
            'data.message',
            'data.error',
            'transaction.message',
        ]);

        $diagnostics = [
            'status' => $this->extractValue($data, ['status', 'data.status', 'transaction.status']),
            'statusAuth' => $this->extractValue($data, ['statusAuth', 'data.statusAuth', 'transaction.statusAuth']),
            'statusClearing' => $this->extractValue($data, ['statusClearing', 'data.statusClearing', 'transaction.statusClearing']),
            'transactionId' => $this->extractValue($data, ['transactionId', 'transaction.id', 'id', 'data.transactionId']),
        ];

        $diagnosticParts = [];
        foreach ($diagnostics as $key => $value) {
            if ($value !== null && $value !== '') {
                $diagnosticParts[] = sprintf('%s=%s', $key, (string) $value);
            }
        }

        if ($reason) {
            return $diagnosticParts
                ? sprintf('%s (%s)', $reason, implode(', ', $diagnosticParts))
                : (string) $reason;
        }

        return $diagnosticParts
            ? sprintf('Gateway response not approved (%s)', implode(', ', $diagnosticParts))
            : 'Gateway response not approved';
    }

    private function extractValue(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalize HelcimPay.js payloads which may arrive as:
     * - raw transaction object
     * - {data: {...}}
     * - {data: {data: {...}}}
     * - event wrapper {eventStatus, eventMessage: {...|"json"}}
     */
    private function normalizeHelcimPayPayload(array $data): array
    {
        $payload = $data;

        if (isset($payload['eventMessage'])) {
            $eventMessage = $payload['eventMessage'];

            if (is_string($eventMessage)) {
                $decoded = json_decode($eventMessage, true);
                if (is_array($decoded)) {
                    $eventMessage = $decoded;
                }
            }

            if (is_array($eventMessage)) {
                $payload = $eventMessage;
            }
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        return $payload;
    }

    /**
     * Non-sensitive structured diagnostics to identify which branch fails in production.
     */
    private function buildAchDiagnostics(array $rawData, array $normalizedData): array
    {
        $rawKeys = array_keys($rawData);
        $normalizedKeys = array_keys($normalizedData);

        return [
            'raw_keys' => array_values(array_slice($rawKeys, 0, 30)),
            'normalized_keys' => array_values(array_slice($normalizedKeys, 0, 30)),
            'raw_event_status' => data_get($rawData, 'eventStatus'),
            'raw_event_name' => data_get($rawData, 'eventName'),
            'normalized_status' => $this->extractValue($normalizedData, ['status', 'statusAuth', 'statusClearing', 'transaction.status']),
            'transaction_id' => $this->extractValue($normalizedData, ['transactionId', 'transaction.id', 'id', 'verification.transactionId']),
            'bank_account_id' => $this->extractValue($normalizedData, ['bankAccountId', 'bankAccount.id', 'bank.id', 'paymentMethod.id', 'account.id']),
            'customer_id' => $this->extractValue($normalizedData, ['customerId', 'customer.id', 'customer.customerId', 'account.customerId']),
            'has_bank_token' => !empty($this->extractValue($normalizedData, ['bankToken', 'token', 'paymentMethod.token', 'bank.token', 'account.token', 'achToken'])),
        ];
    }

    /**
     * Attempt to resolve bankAccountId and customerId from Helcim API using customerCode and bankToken.
     * Helcim ACH verify payloads typically do not include these fields directly,
     * so we fetch them server-side to enable future recurring billing.
     *
     * @return array{0: string|int|null, 1: string|int|null} [$bankAccountId, $customerId]
     */
    private function resolveAchBankAccountDetails(?string $customerCode, ?string $bankToken): array
    {
        if (!$customerCode) {
            return [null, null];
        }

        try {
            $response = $this->helcim_driver->gatewayRequest('/customers', ['search-value' => $customerCode], 'GET');

            $customerList = $response['customers'] ?? (isset($response[0]) ? $response : []);
            if (empty($customerList)) {
                return [null, null];
            }

            $customer = null;
            foreach ($customerList as $c) {
                if (isset($c['customerCode']) && $c['customerCode'] === $customerCode) {
                    $customer = $c;
                    break;
                }
            }
            $customer = $customer ?? $customerList[0];
            $customerId = $customer['customerId'] ?? $customer['id'] ?? null;

            if (!$customerId) {
                return [null, null];
            }

            $accountResponse = $this->helcim_driver->gatewayRequest('/bank-accounts', ['customerId' => $customerId], 'GET');
            $accountList = $accountResponse['bankAccounts'] ?? (isset($accountResponse[0]) ? $accountResponse : []);

            $bankAccountId = null;

            if (!empty($accountList)) {
                foreach ($accountList as $acct) {
                    $acctToken = $acct['bankToken'] ?? $acct['token'] ?? null;
                    if ($bankToken && $acctToken === $bankToken) {
                        $bankAccountId = $acct['bankAccountId'] ?? $acct['id'] ?? null;
                        break;
                    }
                }

                if (!$bankAccountId) {
                    $last = end($accountList);
                    $bankAccountId = $last['bankAccountId'] ?? $last['id'] ?? null;
                }
            }

            return [$bankAccountId, $customerId];
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                [
                    'warning' => 'Could not resolve ACH bankAccountId/customerId from Helcim API — recurring billing may not work',
                    'error' => $e->getMessage(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return [null, null];
        }
    }

    /**
     * Recurring ACH billing via PUT /ach/withdraw
     * Requires bankAccountId and customerId stored in token meta from initial authorization
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $meta = $cgt->meta;
        $bankAccountId = $meta->bankAccountId ?? null;
        $customerId = $meta->customerId ?? null;

        if (!$bankAccountId || !$customerId) {
            throw new PaymentFailed(
                'ACH token is missing required bank account details (bankAccountId, customerId). Please re-authorize your bank account.',
                400
            );
        }

        $amount = $payment_hash->data->amount_with_fee;

        // Map currency code to Helcim currencyId (1=CAD, 2=USD)
        $currencyCode = $this->helcim_driver->client->currency()->code;
        $currencyId = $currencyCode === 'CAD' ? 1 : 2;

        $response = $this->helcim_driver->gatewayRequest('/ach/withdraw', [
            'bankAccountId' => (int) $bankAccountId,
            'customerId' => (int) $customerId,
            'amount' => $amount,
            'currencyId' => $currencyId,
        ], 'PUT');

        $transactionId = $response['transaction']['id'] ?? null;

        if ($transactionId) {
            $data = [
                'payment_type' => PaymentType::ACH,
                'amount' => $amount,
                'transaction_reference' => (string) $transactionId,
                'gateway_type_id' => GatewayType::BANK_TRANSFER,
            ];

            // ACH is asynchronous — pending until settled
            $payment = $this->helcim_driver->createPayment($data, Payment::STATUS_PENDING);

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
            ['error' => 'ACH withdrawal failed', 'response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        throw new PaymentFailed('ACH withdrawal failed', 400);
    }
}
