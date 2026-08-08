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
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ACH implements MethodInterface, LivewireMethodInterface
{
    private const PAYMENT_MODE_BROWSER = 'browser';

    private const PAYMENT_MODE_SAVED_TOKEN = 'saved_token';

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
            $customerCode = $this->ensureAuthorizationCustomer();
            $session = $this->helcim_driver->initializeHelcimPaySession([
                'paymentType' => 'verify',
                'currency'    => $this->helcim_driver->client->currency()->code,
                'amount'      => 0,
                'paymentMethod' => 'ach',
                'customerCode' => $customerCode,
            ]);

            $data['checkout_token'] = $session['checkoutToken'];
            $data['secret_token']   = $session['secretToken'];
            $this->rememberCheckoutSecret(
                $session['secretToken'],
                $this->authorizationContext(),
                ['customer_code' => $customerCode]
            );
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
            return Cache::lock($this->authorizationResponseLockKey((string) $secretToken), 60)->block(
                5,
                fn () => $this->processAuthorizationResponse($request, (string) $transactionData, (string) $secretToken)
            );
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

    private function processAuthorizationResponse(Request $request, string $transactionData, string $secretToken)
    {
        $rawData = json_decode($transactionData, true);

        if (! $rawData) {
            throw new PaymentFailed('Invalid transaction data format', 400);
        }

        $checkout = $this->validateHelcimPayHash($request, $rawData, $this->authorizationContext());
        $expectedCustomerCode = (string) ($checkout['customer_code'] ?? '');
        $clientTransaction = HelcimAchTransaction::from($rawData);

        if ($expectedCustomerCode === '') {
            throw new PaymentFailed('The Helcim bank authorization is not linked to this client.', 400);
        }

        if ($clientTransaction->isFailed()) {
            throw new PaymentFailed(
                'Bank account verification failed: ' . $clientTransaction->statusDescription(),
                400
            );
        }

        $transactionId = $clientTransaction->transactionId;
        if (empty($transactionId)) {
            throw new PaymentFailed('No transactionId returned by HelcimPay.js — cannot verify bank account authorization.', 400);
        }

        $verifiedTransaction = Cache::lock(
            $this->authorizationCustomerLockKey($expectedCustomerCode),
            60
        )->block(5, function () use ($transactionId, $clientTransaction, $expectedCustomerCode): HelcimAchTransaction {
            $verifiedTransaction = $this->helcim_driver->verifyAchTransaction(
                $transactionId,
                0.00,
                $this->helcim_driver->client->currency()->code
            );
            $tokenTransaction = $this->resolveAuthorizedAchReferences(
                HelcimAchTransaction::from(array_merge($clientTransaction->raw, $verifiedTransaction->raw)),
                $expectedCustomerCode
            );
            $this->storeBankToken($tokenTransaction, true);

            return $verifiedTransaction;
        });
        Cache::forget($this->checkoutSecretKey($secretToken));

        SystemLogger::dispatch(
            ['response' => $verifiedTransaction->raw],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Payment view for processing an ACH payment
     */
    public function paymentView(array $data)
    {
        $paymentHash = $this->preparePaymentHash($this->helcim_driver->payment_hash);
        $data['gateway']           = $this->helcim_driver;
        $currency                  = $this->helcim_driver->client->currency()->code;
        $data['amount']            = $paymentHash->data->amount_with_fee;
        $data['currency']          = $currency;
        $data['payment_hash']      = $paymentHash->hash;
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['checkout_fingerprint'] = $this->paymentHashFingerprint($paymentHash);
        $data['payment_mode']      = $this->paymentMode($paymentHash);
        $data['claimed_token_id']  = (string) data_get($paymentHash->data, 'helcim_ach_saved_token_id', '');
        $data['tokens']            = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->get();

        return render('gateways.helcim.ach.pay', $data);
    }

    /**
     * Process ACH payment response
     *
     * HelcimPay.js returns an ACH transaction immediately, but bank clearing is
     * asynchronous. We verify the real transaction and checkout invoice through
     * Helcim's API, store it as pending, and only complete it after clearing.
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
     * A real transactionId and the checkout-specific Helcim invoice association
     * are both required before an Invoice Ninja payment is recorded.
     */
    private function processHelcimPayACHPayment(Request $request, PaymentHash $paymentHash)
    {
        if ($paymentHash->payment_id) {
            $payment = $this->existingPaymentForHash($paymentHash);

            if (! $payment) {
                throw new PaymentFailed('The existing Helcim ACH payment could not be verified.', 409);
            }

            return redirect()->route('client.payments.show', [
                'payment' => $this->helcim_driver->encodePrimaryKey($payment->id),
            ]);
        }

        $transactionData = $request->input('transaction_data');
        $secretToken     = $request->input('secret_token');

        if (empty($transactionData) || empty($secretToken)) {
            throw new PaymentFailed('Invalid ACH payment response', 400);
        }

        $rawData = json_decode($transactionData, true);

        if (! $rawData) {
            throw new PaymentFailed('Invalid transaction data format', 400);
        }

        $checkout = $this->validateHelcimPayHash($request, $rawData, $this->paymentContext($paymentHash));

        $amount   = $paymentHash->data->amount_with_fee;
        $currency = $this->helcim_driver->client->currency()->code;

        $clientTransaction = HelcimAchTransaction::from($rawData);

        if ($clientTransaction->isFailed()) {
            throw new PaymentFailed('ACH payment failed: ' . $clientTransaction->statusDescription(), 400);
        }

        if (! $clientTransaction->transactionId) {
            throw new PaymentFailed('No transactionId returned by HelcimPay.js; the ACH payment was not recorded.', 400);
        }

        $verifiedTransaction = $this->helcim_driver->verifyAchTransaction(
            $clientTransaction->transactionId,
            (float) $amount,
            $currency
        );

        $this->assertTransactionBelongsToCheckout(
            $clientTransaction,
            $verifiedTransaction,
            (string) ($checkout['invoice_number'] ?? '')
        );

        $paymentData = [
            'payment_type'          => PaymentType::ACH,
            'amount'                => $amount,
            'transaction_reference' => $verifiedTransaction->transactionId,
            'gateway_type_id'       => GatewayType::BANK_TRANSFER,
        ];

        $paymentStatus = $verifiedTransaction->isCompleted()
            ? Payment::STATUS_COMPLETED
            : Payment::STATUS_PENDING;

        $payment = $this->createBrowserPayment($paymentData, $paymentStatus, $paymentHash);
        $this->forgetPaymentCheckoutSession($paymentHash, (string) $secretToken);

        $tokenTransaction = $this->resolveReusableAchReferences(
            HelcimAchTransaction::from(array_merge($clientTransaction->raw, $verifiedTransaction->raw))
        );
        if ($tokenTransaction->bankToken || $tokenTransaction->bankAccountId) {
            $this->storeBankToken($tokenTransaction);
        }

        SystemLogger::dispatch(
            ['response' => $verifiedTransaction->raw, 'data' => $paymentData],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        $redirect = redirect()->route('client.payments.show', [
            'payment' => $this->helcim_driver->encodePrimaryKey($payment->id),
        ]);

        return $paymentStatus === Payment::STATUS_PENDING
            ? $redirect->with('message', ctrans('texts.payment_pending_ach'))
            : $redirect;
    }

    /**
     * Process ACH payment with a saved bank token
     */
    private function processTokenPayment(ClientGatewayToken $token, PaymentHash $paymentHash)
    {
        $payment = $this->createSavedTokenPayment($token, $paymentHash);

        return redirect()->route('client.payments.show', [
            'payment' => $this->helcim_driver->encodePrimaryKey($payment->id),
        ]);
    }

    /**
     * Store a bank account token from an ACH response
     */
    private function storeBankToken(HelcimAchTransaction $transaction, bool $required = false): bool
    {
        if (! $transaction->bankAccountId || ! $transaction->customerId) {
            if ($required) {
                throw new PaymentFailed(
                    'Helcim did not return the bank account and customer references required for ACH withdrawals. Please authorize the bank account again.',
                    400
                );
            }

            return false;
        }

        $existingToken = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->where('token', $transaction->bankAccountId)
            ->where('is_deleted', false)
            ->first();

        if ($existingToken) {
            return true;
        }

        $payment_meta        = new \stdClass();
        $payment_meta->brand = $transaction->bankAccountType ?? 'Bank Account';
        $payment_meta->last4 = $transaction->bankAccountNumber ?? '';
        $payment_meta->type  = GatewayType::BANK_TRANSFER;
        $payment_meta->bankAccountId = $transaction->bankAccountId;
        $payment_meta->bankToken = $transaction->bankToken;
        $payment_meta->customerId = $transaction->customerId;
        $payment_meta->customerCode = $transaction->customerCode;

        $tokenData = [
            'payment_meta'      => $payment_meta,
            'token'             => $transaction->bankAccountId,
            'payment_method_id' => GatewayType::BANK_TRANSFER,
        ];

        $this->helcim_driver->storeGatewayToken($tokenData, [
            'gateway_customer_reference' => $transaction->customerId,
        ]);

        return true;
    }

    /**
     * HelcimPay.js commonly returns customerCode/bankToken rather than the IDs
     * required by PUT /ach/withdraw. Resolve only exact API matches; never pick
     * an arbitrary customer or bank account.
     */
    private function resolveReusableAchReferences(HelcimAchTransaction $transaction): HelcimAchTransaction
    {
        if (($transaction->bankAccountId && $transaction->customerId)
            || ! $transaction->customerCode
            || ! $transaction->bankToken) {
            return $transaction;
        }

        try {
            $customerResponse = $this->helcim_driver->gatewayRequest('/customers', [
                'customerCode' => $transaction->customerCode,
            ], 'GET');
            $customers = $customerResponse['customers']
                ?? $customerResponse['data']
                ?? (array_is_list($customerResponse) ? $customerResponse : []);
            $customer = collect($customers)->first(function ($candidate) use ($transaction): bool {
                if (! is_array($candidate)) {
                    return false;
                }

                $code = $candidate['customerCode'] ?? $candidate['code'] ?? null;

                return (string) $code === $transaction->customerCode;
            });
            $customerId = is_array($customer)
                ? ($customer['customerId'] ?? $customer['id'] ?? null)
                : null;

            if (! $customerId) {
                return $transaction;
            }

            $accountResponse = $this->helcim_driver->gatewayRequest(
                "/customers/{$customerId}/bank-accounts",
                [],
                'GET'
            );
            $accounts = $accountResponse['bankAccounts']
                ?? $accountResponse['data']
                ?? (array_is_list($accountResponse) ? $accountResponse : []);
            $account = collect($accounts)->first(function ($candidate) use ($transaction): bool {
                if (! is_array($candidate)) {
                    return false;
                }

                $token = $candidate['bankToken'] ?? $candidate['token'] ?? null;

                return (string) $token === $transaction->bankToken;
            });
            $bankAccountId = is_array($account)
                ? ($account['bankAccountId'] ?? $account['id'] ?? null)
                : null;

            if (! $bankAccountId) {
                return $transaction;
            }

            return HelcimAchTransaction::from(array_merge($transaction->raw, [
                'customerId' => $customerId,
                'bankAccountId' => $bankAccountId,
            ]));
        } catch (\Throwable $e) {
            SystemLogger::dispatch(
                ['warning' => 'Unable to resolve reusable Helcim ACH references', 'error' => $e->getMessage()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_HELCIM,
                $this->helcim_driver->client,
                $this->helcim_driver->client->company
            );

            return $transaction;
        }
    }

    private function resolveAuthorizedAchReferences(
        HelcimAchTransaction $transaction,
        string $expectedCustomerCode
    ): HelcimAchTransaction {
        if (! $transaction->customerCode
            || ! hash_equals($expectedCustomerCode, $transaction->customerCode)) {
            throw new PaymentFailed('The Helcim bank authorization is not linked to this client.', 400);
        }

        $customer = $this->findHelcimCustomer($expectedCustomerCode);
        $customerId = is_array($customer)
            ? ($customer['customerId'] ?? $customer['id'] ?? null)
            : null;

        if (! $customerId || (! $transaction->bankAccountId && ! $transaction->bankToken)) {
            throw new PaymentFailed('The Helcim bank authorization is not linked to this client.', 400);
        }

        $response = $this->helcim_driver->gatewayRequest(
            "/customers/{$customerId}/bank-accounts",
            [],
            'GET'
        );
        $accounts = $response['bankAccounts']
            ?? data_get($response, 'data.bankAccounts')
            ?? $response['data']
            ?? (array_is_list($response) ? $response : []);
        $account = collect($accounts)->first(function ($candidate) use ($transaction): bool {
            if (! is_array($candidate)) {
                return false;
            }

            $candidateId = (string) ($candidate['bankAccountId'] ?? $candidate['id'] ?? '');
            $candidateToken = (string) ($candidate['bankToken'] ?? $candidate['token'] ?? '');

            return (! $transaction->bankAccountId || $candidateId === $transaction->bankAccountId)
                && (! $transaction->bankToken || $candidateToken === $transaction->bankToken);
        });
        $bankAccountId = is_array($account)
            ? ($account['bankAccountId'] ?? $account['id'] ?? null)
            : null;

        if (! $bankAccountId) {
            throw new PaymentFailed('The Helcim bank authorization is not linked to this client.', 400);
        }

        return HelcimAchTransaction::from(array_merge($transaction->raw, [
            'customerId' => $customerId,
            'customerCode' => $expectedCustomerCode,
            'bankAccountId' => $bankAccountId,
        ]));
    }

    private function createSavedTokenPayment(ClientGatewayToken $token, PaymentHash $paymentHash): Payment
    {
        try {
            return Cache::lock($this->paymentOperationLockKey($paymentHash), 60)->block(
                5,
                fn (): Payment => $this->createSavedTokenPaymentWithinLock($token, $paymentHash)
            );
        } catch (LockTimeoutException) {
            throw new PaymentFailed('This Helcim ACH withdrawal is already being processed. Please try again shortly.', 409);
        }
    }

    private function createSavedTokenPaymentWithinLock(ClientGatewayToken $token, PaymentHash $paymentHash): Payment
    {
        $paymentHash->refresh();
        $this->helcim_driver->payment_hash = $paymentHash;

        if ($paymentHash->payment_id) {
            $payment = $this->existingPaymentForHash($paymentHash);

            if (! $payment) {
                throw new PaymentFailed(
                    'The existing Helcim ACH payment could not be verified. Reconcile it before retrying.',
                    409
                );
            }

            return $payment;
        }

        $this->assertClaimedGatewayMatches($paymentHash);

        if ($this->paymentMode($paymentHash) === self::PAYMENT_MODE_BROWSER) {
            throw new PaymentFailed(
                'This payment has already been claimed for a new Helcim bank account.',
                409
            );
        }

        $claimedTokenId = (string) data_get($paymentHash->data, 'helcim_ach_saved_token_id', '');
        if ($this->paymentMode($paymentHash) === self::PAYMENT_MODE_SAVED_TOKEN
            && $claimedTokenId !== (string) $token->id) {
            throw new PaymentFailed(
                'This payment has already been claimed for a different saved Helcim bank account.',
                409
            );
        }

        $meta = $token->meta ?? new \stdClass();
        $bankAccountId = $meta->bankAccountId ?? null;
        $customerId = $meta->customerId ?? null;

        if (! $bankAccountId || ! $customerId) {
            throw new PaymentFailed(
                'This saved Helcim bank account predates ACH withdrawals. Please remove it and authorize the bank account again.',
                400
            );
        }

        $currency = strtoupper($this->helcim_driver->client->currency()->code);
        $currencyId = match ($currency) {
            'CAD' => 1,
            'USD' => 2,
            default => throw new PaymentFailed("Helcim ACH does not support {$currency} saved-token withdrawals.", 400),
        };
        $amount = (float) $paymentHash->data->amount_with_fee;

        $attemptedAt = data_get($paymentHash->data, 'helcim_ach_withdrawal_attempted_at');
        if ($attemptedAt) {
            try {
                $outsideSafeRetryWindow = Carbon::parse((string) $attemptedAt)->lte(now()->subMinutes(4));
            } catch (\Throwable) {
                $outsideSafeRetryWindow = true;
            }

            if ($outsideSafeRetryWindow) {
                throw new PaymentFailed(
                    'A previous Helcim ACH withdrawal has an uncertain result and is outside the safe idempotency window. Reconcile it in Helcim before retrying.',
                    409
                );
            }
        }

        // Claim the saved-token mode before the remote call. If the response is
        // lost, the new-bank flow remains unavailable while this attempt is
        // reconciled or safely retried with the same token/idempotency key.
        $paymentHash->data = array_merge((array) $paymentHash->data, [
            'helcim_ach_payment_mode' => self::PAYMENT_MODE_SAVED_TOKEN,
            'helcim_ach_payment_mode_claimed_at' => data_get(
                $paymentHash->data,
                'helcim_ach_payment_mode_claimed_at',
                now()->toIso8601String()
            ),
            'helcim_ach_payment_data_fingerprint' => $this->paymentHashFingerprint($paymentHash),
            'helcim_ach_company_gateway_id' => (int) $this->helcim_driver->company_gateway->id,
            'helcim_ach_saved_token_id' => (string) $token->id,
            'helcim_ach_withdrawal_attempted_at' => $attemptedAt ?: now()->toIso8601String(),
        ]);
        $paymentHash->save();

        try {
            $response = $this->helcim_driver->gatewayRequest('/ach/withdraw', [
                'bankAccountId' => (int) $bankAccountId,
                'customerId' => (int) $customerId,
                'amount' => $amount,
                'currencyId' => $currencyId,
            ], 'PUT', $this->withdrawalIdempotencyKey($paymentHash));
        } catch (HelcimApiException $e) {
            // These responses conclusively reject the request before creating a
            // transaction, so a later corrected retry is safe. Transport errors,
            // conflicts and server failures remain marked as uncertain.
            if (in_array($e->httpStatus, [400, 401, 403, 404, 422, 429], true)) {
                $this->clearWithdrawalAttempt($paymentHash);
            }

            throw $e;
        }

        $transaction = $this->helcim_driver->validateAchTransaction(
            HelcimAchTransaction::from($response),
            null,
            $amount,
            $currency,
            true,
            false
        );

        $data = [
            'payment_type' => PaymentType::ACH,
            'amount' => $amount,
            'transaction_reference' => $transaction->transactionId,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->helcim_driver->createPayment(
            $data,
            $transaction->isCompleted() ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING
        );

        SystemLogger::dispatch(
            ['response' => $transaction->raw, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_HELCIM,
            $this->helcim_driver->client,
            $this->helcim_driver->client->company
        );

        return $payment;
    }

    private function existingPaymentForHash(PaymentHash $paymentHash): ?Payment
    {
        if (! $paymentHash->payment_id) {
            return null;
        }

        return Payment::withTrashed()
            ->where('id', $paymentHash->payment_id)
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('client_id', $this->helcim_driver->client->id)
            ->first();
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
        $paymentHash = $this->preparePaymentHash($this->helcim_driver->payment_hash, $data);
        $currency = $this->helcim_driver->client->currency()->code;

        $data['gateway']           = $this->helcim_driver;
        $data['payment_hash']      = $paymentHash->hash;
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['amount']            = $paymentHash->data->amount_with_fee;
        $data['currency']          = $currency;
        $data['checkout_fingerprint'] = $this->paymentHashFingerprint($paymentHash);
        $data['payment_mode']      = $this->paymentMode($paymentHash);
        $data['claimed_token_id']  = (string) data_get($paymentHash->data, 'helcim_ach_saved_token_id', '');
        $data['tokens']            = $this->helcim_driver->client->gateway_tokens()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->get();

        return $data;
    }

    /**
     * Claim the new-bank flow and initialize its checkout only after the user
     * explicitly selects it. Saved-token withdrawals use the same mode lock.
     */
    public function initializePaymentCheckout(string $expectedFingerprint): array
    {
        return $this->paymentCheckoutSession(
            $this->helcim_driver->payment_hash,
            $this->helcim_driver->client->currency()->code,
            $expectedFingerprint
        );
    }

    private function preparePaymentHash(PaymentHash $paymentHash, array $updates = []): PaymentHash
    {
        unset(
            $updates['helcim_ach_payment_mode'],
            $updates['helcim_ach_payment_mode_claimed_at'],
            $updates['helcim_ach_payment_data_fingerprint'],
            $updates['helcim_ach_company_gateway_id'],
            $updates['helcim_ach_saved_token_id'],
            $updates['helcim_ach_withdrawal_attempted_at']
        );

        try {
            return Cache::lock($this->paymentOperationLockKey($paymentHash), 60)->block(
                5,
                function () use ($paymentHash, $updates): PaymentHash {
                    $paymentHash->refresh();
                    $this->helcim_driver->payment_hash = $paymentHash;

                    if ($paymentHash->payment_id) {
                        throw new PaymentFailed('This Helcim ACH checkout has already been completed.', 409);
                    }

                    $this->assertClaimedGatewayMatches($paymentHash);

                    $prospectiveData = array_merge((array) $paymentHash->data, $updates);
                    $prospectiveFingerprint = $this->paymentHashFingerprint($paymentHash, $prospectiveData);
                    $claimedFingerprint = (string) data_get(
                        $paymentHash->data,
                        'helcim_ach_payment_data_fingerprint',
                        ''
                    );

                    if ($this->paymentMode($paymentHash) !== null
                        && $claimedFingerprint !== ''
                        && ! hash_equals($claimedFingerprint, $prospectiveFingerprint)) {
                        throw new PaymentFailed(
                            'The payment details cannot be changed after a Helcim ACH payment flow has been selected.',
                            409
                        );
                    }

                    $session = Cache::get($this->paymentCheckoutSessionKey($paymentHash));

                    if (is_array($session)) {
                        $sessionFingerprint = (string) ($session['paymentDataFingerprint'] ?? '');
                        if ($sessionFingerprint === ''
                            || ! hash_equals(
                                $sessionFingerprint,
                                $prospectiveFingerprint
                            )) {
                            throw new PaymentFailed(
                                'The payment details cannot be changed while a Helcim ACH checkout is active.',
                                409
                            );
                        }
                    }

                    if ($updates !== []) {
                        $paymentHash->data = $prospectiveData;
                        $paymentHash->save();
                    }

                    return $paymentHash;
                }
            );
        } catch (LockTimeoutException) {
            throw new PaymentFailed('This Helcim ACH payment is already being prepared. Please try again shortly.', 409);
        }
    }

    /**
     * Process token billing (recurring ACH payments)
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return $this->createSavedTokenPayment($cgt, $payment_hash);
    }

    private function paymentCheckoutSession(
        PaymentHash $paymentHash,
        string $currency,
        string $expectedFingerprint
    ): array
    {
        $cacheKey = $this->paymentCheckoutSessionKey($paymentHash);

        try {
            return Cache::lock($this->paymentOperationLockKey($paymentHash), 60)->block(5, function () use ($cacheKey, $paymentHash, $currency, $expectedFingerprint): array {
                $paymentHash->refresh();
                $this->helcim_driver->payment_hash = $paymentHash;

                if ($paymentHash->payment_id) {
                    throw new PaymentFailed('This Helcim ACH checkout has already been completed.', 409);
                }

                $this->assertClaimedGatewayMatches($paymentHash);

                if ($this->paymentMode($paymentHash) === self::PAYMENT_MODE_SAVED_TOKEN) {
                    throw new PaymentFailed(
                        'This payment has already been claimed for a saved Helcim bank account.',
                        409
                    );
                }

                $amount = (float) $paymentHash->data->amount_with_fee;
                $fingerprint = $this->paymentHashFingerprint($paymentHash);
                if ($expectedFingerprint === '' || ! hash_equals($fingerprint, $expectedFingerprint)) {
                    throw new PaymentFailed(
                        'The payment details changed before the Helcim ACH checkout was initialized. Refresh and try again.',
                        409
                    );
                }

                $existing = Cache::get($cacheKey);

                if (is_array($existing)
                    && isset($existing['checkoutToken'], $existing['secretToken'])
                    && $this->minorUnits((float) ($existing['amount'] ?? 0)) === $this->minorUnits($amount)
                    && strtoupper((string) ($existing['currency'] ?? '')) === strtoupper($currency)
                    && isset($existing['paymentDataFingerprint'])
                    && hash_equals(
                        (string) $existing['paymentDataFingerprint'],
                        $fingerprint
                    )) {
                    $this->claimPaymentMode($paymentHash, self::PAYMENT_MODE_BROWSER);

                    return $existing;
                }

                if ($existing !== null) {
                    throw new PaymentFailed(
                        'The payment details cannot be changed while a Helcim ACH checkout is active. Wait for it to expire before retrying.',
                        409
                    );
                }

                $invoiceNumber = $this->newCheckoutInvoiceNumber();
                $session = $this->helcim_driver->initializeHelcimPaySession([
                    'paymentType' => 'purchase',
                    'amount' => $amount,
                    'currency' => $currency,
                    'paymentMethod' => 'ach',
                    'invoiceRequest' => $this->checkoutInvoiceRequest($invoiceNumber, $amount),
                ]);
                $session['amount'] = $amount;
                $session['currency'] = strtoupper($currency);
                $session['invoiceNumber'] = $invoiceNumber;
                $session['paymentDataFingerprint'] = $fingerprint;

                $this->claimPaymentMode($paymentHash, self::PAYMENT_MODE_BROWSER);
                $this->rememberCheckoutSecret(
                    $session['secretToken'],
                    $this->paymentContext($paymentHash),
                    ['invoice_number' => $invoiceNumber]
                );
                Cache::put($cacheKey, $session, now()->addHour());

                return $session;
            });
        } catch (LockTimeoutException) {
            throw new PaymentFailed('This Helcim ACH checkout is already being initialized. Please try again shortly.', 409);
        }
    }

    private function forgetPaymentCheckoutSession(PaymentHash $paymentHash, string $secretToken): void
    {
        Cache::forget($this->checkoutSecretKey($secretToken));
        Cache::forget($this->paymentCheckoutSessionKey($paymentHash));
    }

    private function paymentHashFingerprint(PaymentHash $paymentHash, ?array $data = null): string
    {
        $data ??= (array) $paymentHash->data;

        return hash_hmac(
            'sha256',
            json_encode([
                'company_gateway_id' => (int) $this->helcim_driver->company_gateway->id,
                'amount_with_fee' => $data['amount_with_fee'] ?? null,
                'invoices' => $data['invoices'] ?? [],
                'credits' => $data['credits'] ?? 0,
                'fee_total' => $paymentHash->fee_total,
                'fee_invoice_id' => $paymentHash->fee_invoice_id,
            ], JSON_THROW_ON_ERROR),
            (string) config('app.key')
        );
    }

    private function minorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function rememberCheckoutSecret(string $secretToken, string $context, array $attributes = []): void
    {
        Cache::put(
            $this->checkoutSecretKey($secretToken),
            array_merge(['context' => $context], $attributes),
            now()->addHour()
        );
    }

    private function validateHelcimPayHash(Request $request, array $transactionData, string $context): array
    {
        $secretToken = (string) $request->input('secret_token', '');
        $transactionHash = (string) $request->input('transaction_hash', '');
        $checkout = Cache::get($this->checkoutSecretKey($secretToken));

        if ($secretToken === ''
            || $transactionHash === ''
            || ! is_array($checkout)
            || ! hash_equals($context, (string) ($checkout['context'] ?? ''))) {
            throw new PaymentFailed('Invalid or expired HelcimPay.js checkout response.', 400);
        }

        $canonicalData = json_encode($transactionData);
        if ($canonicalData === false) {
            throw new PaymentFailed('Invalid HelcimPay.js transaction data.', 400);
        }

        $expectedHash = hash('sha256', $canonicalData . $secretToken);
        if (! hash_equals($expectedHash, strtolower($transactionHash))) {
            throw new PaymentFailed('HelcimPay.js transaction hash validation failed.', 400);
        }

        return $checkout;
    }

    private function assertUnusedTransactionReference(string $transactionId, PaymentHash $paymentHash): void
    {
        $existingPayment = Payment::withTrashed()
            ->where('company_gateway_id', $this->helcim_driver->company_gateway->id)
            ->where('transaction_reference', $transactionId)
            ->first();

        if ($existingPayment && (int) $paymentHash->payment_id !== (int) $existingPayment->id) {
            throw new PaymentFailed('This Helcim ACH transaction has already been recorded.', 400);
        }
    }

    private function createBrowserPayment(array $paymentData, int $paymentStatus, PaymentHash $paymentHash): Payment
    {
        $transactionId = (string) $paymentData['transaction_reference'];

        try {
            return Cache::lock($this->paymentOperationLockKey($paymentHash), 60)->block(5, function () use ($paymentData, $paymentStatus, $paymentHash, $transactionId): Payment {
                $paymentHash->refresh();
                $this->helcim_driver->payment_hash = $paymentHash;

                if ($paymentHash->payment_id) {
                    $payment = $this->existingPaymentForHash($paymentHash);
                    if ($payment) {
                        return $payment;
                    }

                    throw new PaymentFailed('The existing Helcim ACH payment could not be verified.', 409);
                }

                $this->assertClaimedGatewayMatches($paymentHash);

                if ($this->paymentMode($paymentHash) === self::PAYMENT_MODE_SAVED_TOKEN) {
                    throw new PaymentFailed(
                        'This payment has already been claimed for a saved Helcim bank account.',
                        409
                    );
                }

                // Compatibility for a checkout issued immediately before this
                // deployment. New checkouts claim browser mode before exposure.
                $this->claimPaymentMode($paymentHash, self::PAYMENT_MODE_BROWSER);

                $transactionLockKey = 'helcim-ach-transaction:'
                    . $this->helcim_driver->company_gateway->id
                    . ':'
                    . hash('sha256', $transactionId);

                try {
                    return Cache::lock($transactionLockKey, 60)->block(5, function () use ($paymentData, $paymentStatus, $paymentHash, $transactionId): Payment {
                        $this->assertUnusedTransactionReference($transactionId, $paymentHash);

                        return $this->helcim_driver->createPayment($paymentData, $paymentStatus);
                    });
                } catch (LockTimeoutException) {
                    throw new PaymentFailed('This Helcim ACH transaction is already being processed. Please try again shortly.', 409);
                }
            });
        } catch (LockTimeoutException) {
            throw new PaymentFailed('This Helcim ACH payment is already being processed. Please try again shortly.', 409);
        }
    }

    private function assertTransactionBelongsToCheckout(
        HelcimAchTransaction $clientTransaction,
        HelcimAchTransaction $verifiedTransaction,
        string $expectedInvoiceNumber
    ): void {
        if ($expectedInvoiceNumber === ''
            || ! $clientTransaction->invoiceNumber
            || ! hash_equals($expectedInvoiceNumber, $clientTransaction->invoiceNumber)
            || ! $verifiedTransaction->orderId) {
            throw new PaymentFailed('The Helcim ACH transaction is not linked to this checkout.', 400);
        }

        $response = $this->helcim_driver->gatewayRequest('/invoices', [
            'invoiceNumber' => $expectedInvoiceNumber,
        ], 'GET');
        $invoices = $response['invoices']
            ?? $response['data']
            ?? (array_is_list($response) ? $response : []);
        $invoice = collect($invoices)->first(function ($candidate) use ($expectedInvoiceNumber): bool {
            return is_array($candidate)
                && (string) ($candidate['invoiceNumber'] ?? '') === $expectedInvoiceNumber;
        });
        $invoiceId = is_array($invoice) ? ($invoice['invoiceId'] ?? $invoice['id'] ?? null) : null;

        if (! $invoiceId || (string) $invoiceId !== $verifiedTransaction->orderId) {
            throw new PaymentFailed('The Helcim ACH transaction is not linked to this checkout.', 400);
        }
    }

    private function checkoutInvoiceRequest(string $invoiceNumber, float $amount): array
    {
        return [
            'invoiceNumber' => $invoiceNumber,
            'lineItems' => [[
                'sku' => 'IN-ACH',
                'description' => 'Invoice Ninja ACH payment',
                'quantity' => 1,
                'price' => $amount,
                'total' => $amount,
            ]],
        ];
    }

    private function newCheckoutInvoiceNumber(): string
    {
        return 'IN-' . substr(bin2hex(random_bytes(16)), 0, 24);
    }

    private function paymentOperationLockKey(PaymentHash $paymentHash): string
    {
        return 'helcim-ach-payment-operation:' . hash('sha256', $paymentHash->hash);
    }

    private function paymentMode(PaymentHash $paymentHash): ?string
    {
        $mode = data_get($paymentHash->data, 'helcim_ach_payment_mode');

        return is_string($mode) && $mode !== '' ? $mode : null;
    }

    private function assertClaimedGatewayMatches(PaymentHash $paymentHash): void
    {
        $claimedGatewayId = data_get($paymentHash->data, 'helcim_ach_company_gateway_id');

        if ($claimedGatewayId !== null
            && (int) $claimedGatewayId !== (int) $this->helcim_driver->company_gateway->id) {
            throw new PaymentFailed(
                'This Helcim ACH payment has already been claimed by another gateway configuration.',
                409
            );
        }
    }

    private function claimPaymentMode(PaymentHash $paymentHash, string $mode): void
    {
        $this->assertClaimedGatewayMatches($paymentHash);
        $existingMode = $this->paymentMode($paymentHash);
        if ($existingMode !== null && $existingMode !== $mode) {
            throw new PaymentFailed('This Helcim ACH payment has already been claimed by another payment flow.', 409);
        }

        $fingerprint = $this->paymentHashFingerprint($paymentHash);
        $claimedFingerprint = (string) data_get(
            $paymentHash->data,
            'helcim_ach_payment_data_fingerprint',
            ''
        );
        if ($claimedFingerprint !== '' && ! hash_equals($claimedFingerprint, $fingerprint)) {
            throw new PaymentFailed('The details for this Helcim ACH payment claim no longer match.', 409);
        }

        if ($existingMode === $mode && $claimedFingerprint !== '') {
            return;
        }

        $paymentHash->data = array_merge((array) $paymentHash->data, [
            'helcim_ach_payment_mode' => $mode,
            'helcim_ach_payment_mode_claimed_at' => now()->toIso8601String(),
            'helcim_ach_payment_data_fingerprint' => $fingerprint,
            'helcim_ach_company_gateway_id' => (int) $this->helcim_driver->company_gateway->id,
        ]);
        $paymentHash->save();
    }

    private function checkoutSecretKey(string $secretToken): string
    {
        return 'helcim-ach-checkout:' . hash('sha256', $secretToken);
    }

    private function paymentCheckoutSessionKey(PaymentHash $paymentHash): string
    {
        return 'helcim-ach-payment-session:'
            . $this->helcim_driver->company_gateway->id
            . ':'
            . hash('sha256', $paymentHash->hash);
    }

    private function authorizationResponseLockKey(string $secretToken): string
    {
        return 'helcim-ach-authorization-response:' . hash('sha256', $secretToken);
    }

    private function authorizationCustomerLockKey(string $customerCode): string
    {
        return 'helcim-ach-authorization-customer:'
            . $this->helcim_driver->company_gateway->id
            . ':'
            . hash('sha256', $customerCode);
    }

    private function authorizationCustomerCode(): string
    {
        return 'IN-' . substr(hash(
            'sha256',
            "helcim-ach-customer:{$this->helcim_driver->company_gateway->id}:{$this->helcim_driver->client->id}"
        ), 0, 24);
    }

    private function ensureAuthorizationCustomer(): string
    {
        $customerCode = $this->authorizationCustomerCode();

        if ($this->findHelcimCustomer($customerCode)) {
            return $customerCode;
        }

        $name = trim((string) $this->helcim_driver->client->name);
        $name = $name !== '' ? $name : "Invoice Ninja client {$this->helcim_driver->client->hashed_id}";

        try {
            $this->helcim_driver->gatewayRequest('/customers', [
                'customerCode' => $customerCode,
                'businessName' => mb_substr($name, 0, 100),
            ]);
        } catch (HelcimApiException $e) {
            // A concurrent checkout can create the same deterministic customer.
            // Only tolerate a conflict when an exact refetch proves it exists.
            if ($e->httpStatus !== 409 || ! $this->findHelcimCustomer($customerCode)) {
                throw $e;
            }
        }

        if (! $this->findHelcimCustomer($customerCode)) {
            throw new PaymentFailed('Helcim could not create the customer required for bank authorization.', 400);
        }

        return $customerCode;
    }

    /**
     * @phpstan-impure Performs a fresh remote API lookup on every call.
     */
    private function findHelcimCustomer(string $customerCode): ?array
    {
        $response = $this->helcim_driver->gatewayRequest('/customers', [
            'customerCode' => $customerCode,
        ], 'GET');
        $customers = $response['customers']
            ?? data_get($response, 'data.customers')
            ?? $response['data']
            ?? (array_is_list($response) ? $response : []);
        $customer = collect($customers)->first(function ($candidate) use ($customerCode): bool {
            return is_array($candidate)
                && (string) ($candidate['customerCode'] ?? $candidate['code'] ?? '') === $customerCode;
        });

        return is_array($customer) ? $customer : null;
    }

    private function clearWithdrawalAttempt(PaymentHash $paymentHash): void
    {
        $data = (array) $paymentHash->data;
        unset(
            $data['helcim_ach_payment_mode'],
            $data['helcim_ach_payment_mode_claimed_at'],
            $data['helcim_ach_payment_data_fingerprint'],
            $data['helcim_ach_company_gateway_id'],
            $data['helcim_ach_saved_token_id'],
            $data['helcim_ach_withdrawal_attempted_at']
        );
        $paymentHash->data = $data;
        $paymentHash->save();
    }

    private function authorizationContext(): string
    {
        return "authorization:{$this->helcim_driver->company_gateway->id}:{$this->helcim_driver->client->id}";
    }

    private function paymentContext(PaymentHash $paymentHash): string
    {
        return "payment:{$this->helcim_driver->company_gateway->id}:{$this->helcim_driver->client->id}:{$paymentHash->id}";
    }

    private function withdrawalIdempotencyKey(PaymentHash $paymentHash): string
    {
        return substr(hash(
            'sha256',
            "helcim-ach-withdraw:{$this->helcim_driver->company_gateway->id}:{$paymentHash->hash}"
        ), 0, 32);
    }
}
