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

namespace App\PaymentDrivers\CheckoutCom;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\Utils\Traits\MakesHash;
use Checkout\CheckoutApiException;
use Checkout\CheckoutArgumentException;
use Checkout\CheckoutAuthorizationException;
use Checkout\Payments\Previous\PaymentRequest as PreviousPaymentRequest;
use Checkout\Payments\Previous\Source\RequestTokenSource;
use Checkout\Payments\Request\PaymentRequest;
use Checkout\Payments\Request\Source\RequestTokenSource as SourceRequestTokenSource;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCard implements MethodInterface, LivewireMethodInterface
{
    use Utilities;
    use MakesHash;

    /**
     * @var CheckoutComPaymentDriver
     */
    public $checkout;

    public function __construct(CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;

        $this->checkout->init();
    }

    /**
     * An authorization view for credit card.
     *
     * @param mixed $data
     * @return Factory|View
     */
    public function authorizeView($data)
    {
        $data['gateway'] = $this->checkout;
        $data['cardholder_name'] = auth()->guard('contact')->user()->present()->name() ?? '';

        if ($this->checkout->useFlow()) {
            $amount = (int) $this->checkout->convertToCheckoutAmount(1, $this->checkout->client->getCurrencyCode());
            $amount = max(100, $amount); // at least 100 in minor units ($1.00 equivalent)
            // Route back through the authorize handler so cko-payment-id is captured if 3DS full-page redirects
            $successUrl = route('client.payment_methods.confirm', ['method' => GatewayType::CREDIT_CARD]);
            $failureUrl = route('client.payment_methods.confirm', ['method' => GatewayType::CREDIT_CARD]);
            $session = $this->checkout->createPaymentSession(
                $amount,
                'Card authorization',
                'authorize',
                $successUrl,
                $failureUrl,
                false
            );
            $data['payment_session_id'] = $session['id'];
            $data['payment_session_token'] = $session['payment_session_token'] ?? $session['payment_session_secret'] ?? '';
            $data['use_flow'] = true;
            $data['environment'] = $this->checkout->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production';
        } else {
            $data['use_flow'] = false;
        }

        return render('gateways.checkout.credit_card.authorize', $data);
    }

    public function bootRequest($token)
    {
        if ($this->checkout->is_four_api) {
            $token_source = new RequestTokenSource();
            $token_source->token = $token;
            $request = new PreviousPaymentRequest();
            $request->source = $token_source;
        } else {
            $token_source = new SourceRequestTokenSource();
            $token_source->token = $token;
            $request = new PaymentRequest();
            $request->source = $token_source;
        }

        return $request;
    }

    /**
     * Handle authorization for credit card.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse(Request $request)
    {
        $gateway_response = json_decode($request->gateway_response, true);

        // Flow SDK 3DS full-page redirect: Checkout.com appends cko-payment-id to success_url
        if (empty($request->gateway_response) && $request->filled('cko-payment-id')) {
            return $this->authorizeResponseFlow($request->input('cko-payment-id'));
        }

        // Flow SDK JS callback: onPaymentCompleted submits gateway_response with payment id
        if (is_array($gateway_response) && isset($gateway_response['id']) && ! isset($gateway_response['token'])) {
            return $this->authorizeResponseFlow($gateway_response['id']);
        }

        $gateway_response = (object) $gateway_response;
        $customerRequest = $this->checkout->getCustomer();

        $paymentRequest = $this->bootRequest($gateway_response->token);
        $paymentRequest->capture = false;
        $paymentRequest->reference = '$1 payment for authorization.';
        $paymentRequest->amount = 100;
        $paymentRequest->currency = $this->checkout->client->getCurrencyCode();
        $paymentRequest->customer = $customerRequest;

        try {
            $response = $this->checkout->gateway->getPaymentsClient()->requestPayment($paymentRequest);

            if (isset($response['approved']) && $response['status'] === 'Authorized') {
                $payment_meta = new \stdClass();
                $payment_meta->exp_month = (string) $response['source']['expiry_month'];
                $payment_meta->exp_year = (string) $response['source']['expiry_year'];
                $payment_meta->brand = (string) $response['source']['scheme'];
                $payment_meta->last4 = (string) $response['source']['last4'];
                $payment_meta->type = (int) GatewayType::CREDIT_CARD;

                $data = [
                    'payment_meta' => $payment_meta,
                    'token' => $response['source']['id'],
                    'payment_method_id' => GatewayType::CREDIT_CARD,
                ];

                $payment_method = $this->checkout->storeGatewayToken($data, ['gateway_customer_reference' => $customerRequest['id']]);

                return redirect()->route('client.payment_methods.show', $payment_method->hashed_id);
            }
        } catch (CheckoutApiException $e) {
            $error_details = $e->getMessage();
            if (is_array($e->error_details ?? null)) {
                $raw = $e->error_details['error_codes'] ?? $e->error_details;
                $error_details = is_array($raw) ? (is_array(end($raw)) ? json_encode($raw) : (string) end($raw)) : (string) $raw;
            }
            throw new PaymentFailed(is_string($error_details) ? $error_details : json_encode($error_details), $e->getCode());
        } catch (CheckoutArgumentException $e) {
            // Bad arguments
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization

            throw new PaymentFailed("There is a problem with your Checkout Gateway API keys", 401);
        }
    }

    /**
     * Handle Flow SDK authorization response: get payment details, extract source id, void auth, store token.
     */
    private function authorizeResponseFlow(string $paymentId): \Illuminate\Http\RedirectResponse
    {
        try {
            $payment = $this->checkout->gateway->getPaymentsClient()->getPaymentDetails($paymentId);

            if (! (isset($payment['approved']) && $payment['approved'])) {
                throw new PaymentFailed($payment['response_summary'] ?? 'Authorization was not approved.', 400);
            }

            $this->checkout->gateway->getPaymentsClient()->voidPayment($paymentId);

            if (empty($payment['source']['id'] ?? null)) {
                throw new PaymentFailed('Authorization succeeded but no instrument ID was returned. Card could not be saved.', 400);
            }

            $customerRequest = $this->checkout->getCustomer();
            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) ($payment['source']['expiry_month'] ?? '');
            $payment_meta->exp_year = (string) ($payment['source']['expiry_year'] ?? '');
            $payment_meta->brand = (string) ($payment['source']['scheme'] ?? '');
            $payment_meta->last4 = (string) ($payment['source']['last4'] ?? '');
            $payment_meta->type = (int) GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $payment['source']['id'],
                'payment_method_id' => GatewayType::CREDIT_CARD,
            ];

            $payment_method = $this->checkout->storeGatewayToken($data, ['gateway_customer_reference' => $customerRequest['id']]);

            return redirect()->route('client.payment_methods.show', $payment_method->hashed_id);
        } catch (CheckoutApiException $e) {
            $error_details = $e->error_details;
            if (isset($e->error_details['error_codes']) && is_array($e->error_details['error_codes'])) {
                $error_details = end($e->error_details['error_codes']);
            } else {
                $error_details = $e->getMessage();
            }
            throw new PaymentFailed($error_details, $e->getCode());
        } catch (CheckoutArgumentException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        } catch (CheckoutAuthorizationException $e) {
            throw new PaymentFailed('There is a problem with your Checkout Gateway API keys', 401);
        }
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->checkout;
        $data['company_gateway'] = $this->checkout->company_gateway;
        $data['client'] = $this->checkout->client;
        $data['currency'] = $this->checkout->client->getCurrencyCode();
        $data['value'] = $this->checkout->convertToCheckoutAmount($data['total']['amount_with_fee'], $this->checkout->client->getCurrencyCode());
        $data['raw_value'] = $data['total']['amount_with_fee'];
        $data['customer_email'] = $this->checkout->client->present()->email();
        $data['cardholder_name'] = auth()->guard('contact')->user()->present()->name() ?? '';

        if ($this->checkout->useFlow()) {
            try {
                $paymentHash = $data['payment_hash'];

                // Mirror the token_billing_string logic from save_card.blade.php so that
                // when Checkout.com redirects back via success_url (3DS), the store_card
                // value arrives as a GET param — exactly as it would from a form POST.
                $tokenBilling = $this->checkout->company_gateway->token_billing ?? 'off';
                $storeCardParam = in_array($tokenBilling, ['always', 'optout']) ? 'true' : 'false';

                $successUrl = route('client.payments.response.get', [
                    'company_key'        => $this->checkout->client->company->company_key,
                    'payment_hash'       => $paymentHash,
                    'company_gateway_id' => $this->checkout->company_gateway->id,
                    'store_card'         => $storeCardParam,
                ]);
                $failureUrl = $successUrl;

                $session = $this->checkout->createPaymentSession(
                    (int) $data['value'],
                    substr($this->checkout->getDescription(), 0, 49),
                    $paymentHash,
                    $successUrl,
                    $failureUrl,
                    true
                );

                $data['payment_session_id'] = $session['id'];
                $data['payment_session_token'] = $session['payment_session_token'] ?? $session['payment_session_secret'] ?? '';
                $data['use_flow'] = true;
                $data['environment'] = $this->checkout->company_gateway->getConfigField('testMode') ? 'sandbox' : 'production';

                // Persist payment values into the hash so the 3DS GET redirect can complete the payment
                $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, [
                    'raw_value'         => $data['raw_value'],
                    'value'             => $data['value'],
                    'currency'          => $data['currency'],
                    'payment_method_id' => $data['payment_method_id'] ?? GatewayType::CREDIT_CARD,
                ]);
                $this->checkout->payment_hash->save();
            } catch (CheckoutApiException $e) {
                $msg = $e->getMessage();
                nlog($e->getMessage());
                if (is_array($e->error_details ?? null)) {
                    $raw = $e->error_details['error_message'] ?? $e->error_details['message'] ?? $e->error_details;
                    $msg = is_array($raw) ? json_encode($raw) : (string) $raw;
                }
                
                throw new PaymentFailed('Unable to initialize payment. '.$msg, $e->getCode() ?: 400);
            } catch (CheckoutArgumentException|CheckoutAuthorizationException $e) {
                throw new PaymentFailed($e->getMessage(), $e->getCode() ?: 400);
            } catch (\InvalidArgumentException $e) {
                throw new PaymentFailed($e->getMessage(), 400);
            }
        } else {
            $data['use_flow'] = false;
        }

        return $data;
    }

    public function paymentView($data, $livewire = false)
    {
        $data = $this->paymentData($data);

        if ($livewire) {
            return render('gateways.checkout.credit_card.pay_livewire', $data);
        }

        return render('gateways.checkout.credit_card.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.checkout.credit_card.pay_livewire';
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        nlog(['checkout_flow_payment_response_request' => $request->all()]);

        $gatewayResponseRaw = $request->gateway_response;
        $state = [
            'server_response' => $gatewayResponseRaw ? json_decode($gatewayResponseRaw) : null,
            'value' => $request->value,
            'raw_value' => $request->raw_value,
            'currency' => $request->currency,
            'payment_hash' => $request->payment_hash,
            'client_id' => $this->checkout->client->id,
        ];

        $state = array_merge($state, $request->all());

        // Only override store_card when it was explicitly submitted (POST or GET param).
        // The value arrives as the string "true"/"false" (from form or success_url),
        // so we filter it explicitly — boolval("false") === true in PHP.
        if ($request->has('store_card')) {
            $state['store_card'] = filter_var($request->input('store_card'), FILTER_VALIDATE_BOOLEAN);
        } else {
            unset($state['store_card']);
        }

        // Only merge non-null values so GET redirects don't overwrite values
        // pre-saved into the hash during session creation (raw_value, value, currency, etc.)
        $stateForHash = array_filter($state, fn ($v) => $v !== null);
        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, $stateForHash);
        $this->checkout->payment_hash->save();

        if ($request->has('token') && ! is_null($request->token) && ! empty($request->token)) {
            return $this->attemptPaymentUsingToken($request);
        }

        // Flow SDK 3DS redirect: Checkout.com appends cko-payment-id to success_url
        if (empty($gatewayResponseRaw) && $request->filled('cko-payment-id')) {
            return $this->attemptPaymentUsingFlowResponse($request, $request->input('cko-payment-id'));
        }

        // Flow SDK JS callback: onPaymentCompleted submits gateway_response with payment id
        $gatewayResponse = $gatewayResponseRaw ? json_decode($gatewayResponseRaw, true) : null;
        if (is_array($gatewayResponse) && isset($gatewayResponse['id']) && ! isset($gatewayResponse['token'])) {
            return $this->attemptPaymentUsingFlowResponse($request);
        }

        // Require gateway_response for new card payment (Frames)
        if (empty($gatewayResponseRaw) || (is_array($gatewayResponse) && empty($gatewayResponse))) {
            throw new PaymentFailed('Payment could not be completed: no payment data was received. Please try again.', 400);
        }

        return $this->attemptPaymentUsingCreditCard($request);
    }

    private function attemptPaymentUsingToken(PaymentResponseRequest $request)
    {
        $cgt = ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($request->input('token')))
            ->where('company_id', auth()->guard('contact')->user()->client->company_id)
            ->first();

        if (! $cgt) {
            throw new PaymentFailed(ctrans('texts.payment_token_not_found'), 401);
        }

        $paymentRequest = $this->checkout->bootTokenRequest($cgt->token);

        return $this->completePayment($paymentRequest, $request, true);
    }

    private function attemptPaymentUsingFlowResponse(PaymentResponseRequest $request, ?string $paymentId = null)
    {
        $gatewayResponse = json_decode($request->gateway_response, true);
        $paymentId = $paymentId ?? ($gatewayResponse['id'] ?? null);

        nlog(['checkout_flow_submission' => $gatewayResponse, 'payment_id' => $paymentId]);

        if (! $paymentId) {
            nlog('checkout_flow: missing payment id in gateway_response');
            return $this->checkout->processUnsuccessfulPayment(
                ['status' => 'Flow payment response missing id'],
                true
            );
        }

        try {
            $payment = $this->checkout->gateway->getPaymentsClient()->getPaymentDetails($paymentId);

            nlog(['checkout_flow_payment_details' => $payment]);

            if (isset($payment['approved']) && $payment['approved']) {
                return $this->processSuccessfulPayment($payment);
            }

            return $this->processUnsuccessfulPayment($payment, true);
        } catch (CheckoutApiException $e) {
            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
            return $this->checkout->processInternallyFailedPayment($this->checkout, $e);
        } catch (CheckoutArgumentException|CheckoutAuthorizationException $e) {
            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    private function attemptPaymentUsingCreditCard(PaymentResponseRequest $request)
    {
        $checkout_response = $this->checkout->payment_hash->data->server_response;

        $paymentRequest = $this->bootRequest($checkout_response->token);

        return $this->completePayment($paymentRequest, $request);
    }

    private function completePayment($paymentRequest, PaymentResponseRequest $request, bool $isTokenPayment = false)
    {
        $paymentRequest->amount = $this->checkout->payment_hash->data->value;
        $paymentRequest->reference = substr($this->checkout->getDescription(), 0, 49);
        $paymentRequest->customer = $this->checkout->getCustomer();
        $paymentRequest->metadata = ['udf1' => 'Invoice Ninja', 'udf2' => $this->checkout->payment_hash->hash];
        $paymentRequest->currency = $this->checkout->client->getCurrencyCode();

        $processingChannelId = $this->checkout->company_gateway->getConfigField('processingChannelId');
        if ($processingChannelId) {
            $paymentRequest->processing_channel_id = $processingChannelId;
        }

        // Token (stored card) payments are merchant-initiated recurring transactions —
        // 3DS exemptions apply and the challenge loop must be avoided.
        if ($isTokenPayment) {
            $paymentRequest->payment_type = 'Recurring';
        }

        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, ['checkout_payment_ref' => $paymentRequest]);
        $this->checkout->payment_hash->save();

        if (! $isTokenPayment && ($this->checkout->client->currency()->code == 'EUR' || $this->checkout->company_gateway->getConfigField('threeds'))) {
            $paymentRequest->{'3ds'} = ['enabled' => true];

            $paymentRequest->{'success_url'} = route('checkout.3ds_redirect', [
                'company_key' => $this->checkout->client->company->company_key,
                'company_gateway_id' => $this->checkout->company_gateway->hashed_id,
                'hash' => $this->checkout->payment_hash->hash,
            ]);

            $paymentRequest->{'failure_url'} = route('checkout.3ds_redirect', [
                'company_key' => $this->checkout->client->company->company_key,
                'company_gateway_id' => $this->checkout->company_gateway->hashed_id,
                'hash' => $this->checkout->payment_hash->hash,
            ]);
        }

        try {
            $response = $this->checkout->gateway->getPaymentsClient()->requestPayment($paymentRequest);

            if ($this->checkout->company_gateway->update_details && isset($response['customer'])) {
                $this->checkout->updateCustomer($response['customer']['id'] ?? '');
            }

            if ($response['status'] == 'Authorized') {

                return $this->processSuccessfulPayment($response);
            }

            if ($response['status'] == 'Pending') {

                $data = [
                    'gateway_type_id' => GatewayType::CREDIT_CARD,
                ];

                $this->checkout->confirmGatewayFee($data);

                return $this->processPendingPayment($response);
            }

            if ($response['status'] == 'Declined') {
                $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

                //18-10-2022
                SystemLogger::dispatch(
                    $response,
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_ERROR,
                    SystemLog::TYPE_CHECKOUT,
                    $this->checkout->client,
                    $this->checkout->client->company,
                );

                return $this->processUnsuccessfulPayment($response);
            }

            // Captured is returned when auto-capture is enabled on the gateway
            if ($response['status'] == 'Captured' && isset($response['approved']) && $response['approved']) {
                return $this->processSuccessfulPayment($response);
            }

            // Unrecognised status — treat as failure
            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
            return $this->processUnsuccessfulPayment($response);

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;

            if (is_array($error_details)) {
                $error_details = end($e->error_details['error_codes']);

                SystemLogger::dispatch(
                    $error_details,
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_ERROR,
                    SystemLog::TYPE_CHECKOUT,
                    $this->checkout->client,
                    $this->checkout->client->company,
                );

            }

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            $human_message = is_array($error_details) ? json_encode($error_details) : (string) $error_details;
            $human_exception = $human_message !== '' ? new \Exception($human_message, 400) : $e;

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_CHECKOUT,
                $this->checkout->client,
                $this->checkout->client->company,
            );

            return $this->checkout->processInternallyFailedPayment($this->checkout, $human_exception);
        } catch (CheckoutArgumentException $e) {
            // Bad arguments

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_CHECKOUT,
                $this->checkout->client,
                $this->checkout->client->company,
            );

            return new PaymentFailed($e->getMessage(), $e->getCode());

        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_CHECKOUT,
                $this->checkout->client,
                $this->checkout->client->company,
            );

            return new PaymentFailed("There was a problem communicating with the API credentials for Checkout", $e->getCode());

        }
    }
}
