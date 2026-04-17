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

use App\Models\GatewayType;
use Checkout\Common\Address;
use Illuminate\Http\Request;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use Checkout\CheckoutApiException;
use Checkout\Payments\ThreeDsRequest;
use Checkout\CheckoutArgumentException;
use Checkout\Payments\BillingInformation;
use Checkout\CheckoutAuthorizationException;
use Checkout\Payments\PaymentCustomerRequest;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use Checkout\Payments\Sessions\Card as SessionCard;
use Checkout\Payments\Sessions\PaymentSessionsRequest;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use Checkout\Payments\Sessions\PaymentMethodConfiguration;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class CreditCardFlow implements MethodInterface, LivewireMethodInterface
{
    use Utilities;
    use MakesHash;

    public CheckoutComPaymentDriver $checkout;

    public function __construct(CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;
        $this->checkout->init();
    }

    /**
     * Authorization view — renders the Flow SDK card form.
     */
    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->checkout;
        $data['cardholder_name'] = auth()->guard('contact')->user()->present()->name() ?? '';

        $amount = (int) $this->checkout->convertToCheckoutAmount(1, $this->checkout->client->getCurrencyCode());
        $amount = max(100, $amount);

        $successUrl = route('client.payment_methods.confirm', ['method' => GatewayType::CREDIT_CARD]);
        $failureUrl = route('client.payment_methods.confirm', ['method' => GatewayType::CREDIT_CARD]);

        $session = $this->createPaymentSession(
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

        return render('gateways.checkout.credit_card.authorize', $data);
    }

    /**
     * Handle authorization response from the Flow SDK.
     */
    public function authorizeResponse(Request $request)
    {
        // Flow SDK 3DS full-page redirect: Checkout.com appends cko-payment-id to success_url
        if (empty($request->gateway_response) && $request->filled('cko-payment-id')) {
            return $this->authorizeResponseFlow($request->input('cko-payment-id'));
        }

        // Flow SDK JS callback: onPaymentCompleted submits gateway_response with payment id
        $gateway_response = json_decode($request->gateway_response, true);
        if (is_array($gateway_response) && isset($gateway_response['id']) && !isset($gateway_response['token'])) {
            return $this->authorizeResponseFlow($gateway_response['id']);
        }

        throw new PaymentFailed('No valid Flow payment response received.', 400);
    }

    /**
     * Get payment details, void the auth, and store the card token.
     */
    private function authorizeResponseFlow(string $paymentId): \Illuminate\Http\RedirectResponse
    {
        try {
            $payment = $this->checkout->gateway->getPaymentsClient()->getPaymentDetails($paymentId);

            if (!(isset($payment['approved']) && $payment['approved'])) {
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

    /**
     * Build the data array for the payment view, including the Flow session.
     */
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

        try {
            $paymentHash = $data['payment_hash'];

            $tokenBilling = $this->checkout->company_gateway->token_billing ?? 'off';
            $storeCardParam = in_array($tokenBilling, ['always', 'optout']) ? 'true' : 'false';

            $successUrl = route('client.payments.response.get', [
                'company_key'        => $this->checkout->client->company->company_key,
                'payment_hash'       => $paymentHash,
                'company_gateway_id' => $this->checkout->company_gateway->id,
                'store_card'         => $storeCardParam,
            ]);
            $failureUrl = $successUrl;

            $session = $this->createPaymentSession(
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

            $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, [
                'raw_value'         => $data['raw_value'],
                'value'             => $data['value'],
                'currency'          => $data['currency'],
                'payment_method_id' => $data['payment_method_id'] ?? GatewayType::CREDIT_CARD,
                'gateway_type_id'   => $this->checkout->gateway_type_id,
            ]);
            $this->checkout->payment_hash->save();
        } catch (CheckoutApiException $e) {
            $msg = $e->getMessage();
            nlog($e->getMessage());
            $errorCodes = [];
            if (is_array($e->error_details ?? null)) {
                $errorCodes = $e->error_details['error_codes'] ?? [];
                $raw = $e->error_details['error_message'] ?? $e->error_details['message'] ?? $e->error_details;
                $msg = is_array($raw) ? json_encode($raw) : (string) $raw;
            }

            if (in_array('no_payment_methods_available', $errorCodes)) {
                throw new PaymentFailed('This payment method is not available for this merchant. Please select a different payment method.', 400);
            }

            throw new PaymentFailed('Unable to initialize payment. ' . $msg, $e->getCode() ?: 400);
        } catch (CheckoutArgumentException|CheckoutAuthorizationException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode() ?: 400);
        } catch (\InvalidArgumentException $e) {
            throw new PaymentFailed($e->getMessage(), 400);
        }

        return $data;
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.checkout.credit_card.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.checkout.credit_card.pay_livewire';
    }

    /**
     * Handle the payment response — token payments or Flow completions.
     */
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

        if ($request->has('store_card')) {
            $state['store_card'] = filter_var($request->input('store_card'), FILTER_VALIDATE_BOOLEAN);
        } else {
            unset($state['store_card']);
        }

        $stateForHash = array_filter($state, fn ($v) => $v !== null);
        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, $stateForHash);
        $this->checkout->payment_hash->save();

        // Token payment (stored card)
        if ($request->has('token') && !is_null($request->token) && !empty($request->token)) {
            return $this->attemptPaymentUsingToken($request);
        }

        // Flow SDK 3DS redirect: Checkout.com appends cko-payment-id to success_url
        if (empty($gatewayResponseRaw) && $request->filled('cko-payment-id')) {
            return $this->attemptPaymentUsingFlowResponse($request, $request->input('cko-payment-id'));
        }

        // Flow SDK JS callback: onPaymentCompleted submits gateway_response with payment id
        $gatewayResponse = $gatewayResponseRaw ? json_decode($gatewayResponseRaw, true) : null;
        if (is_array($gatewayResponse) && isset($gatewayResponse['id']) && !isset($gatewayResponse['token'])) {
            return $this->attemptPaymentUsingFlowResponse($request);
        }

        throw new PaymentFailed('Payment could not be completed: no payment data was received. Please try again.', 400);
    }

    private function attemptPaymentUsingToken(PaymentResponseRequest $request)
    {
        $cgt = ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($request->input('token')))
            ->where('client_id', $this->checkout->client->id)
            ->firstOrFail();

        $paymentRequest = $this->checkout->bootTokenRequest($cgt->token);
        $paymentRequest->amount = $this->checkout->payment_hash->data->value;
        $paymentRequest->reference = substr($this->checkout->getDescription(), 0, 49);
        $paymentRequest->customer = $this->checkout->getCustomer();
        $paymentRequest->metadata = ['udf1' => 'Invoice Ninja', 'udf2' => $this->checkout->payment_hash->hash];
        $paymentRequest->currency = $this->checkout->client->getCurrencyCode();

        $processingChannelId = $this->checkout->company_gateway->getConfigField('processingChannelId');
        if ($processingChannelId) {
            $paymentRequest->processing_channel_id = $processingChannelId;
        }

        $paymentRequest->payment_type = 'Recurring';

        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, ['checkout_payment_ref' => $paymentRequest]);
        $this->checkout->payment_hash->save();

        try {
            $response = $this->checkout->gateway->getPaymentsClient()->requestPayment($paymentRequest);

            if ($this->checkout->company_gateway->update_details && isset($response['customer'])) {
                $this->checkout->updateCustomer($response['customer']['id'] ?? '');
            }

            if ($response['status'] == 'Authorized' || $response['status'] == 'Captured') {
                return $this->processSuccessfulPayment($response);
            }

            if ($response['status'] == 'Declined') {
                $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
                return $this->processUnsuccessfulPayment($response);
            }

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
            return $this->processUnsuccessfulPayment($response);
        } catch (CheckoutApiException $e) {
            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
            return $this->checkout->processInternallyFailedPayment($this->checkout, $e);
        } catch (CheckoutArgumentException|CheckoutAuthorizationException $e) {
            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    private function attemptPaymentUsingFlowResponse(PaymentResponseRequest $request, ?string $paymentId = null)
    {
        $gatewayResponse = json_decode($request->gateway_response ?? '', true);
        $paymentId = $paymentId ?? ($gatewayResponse['id'] ?? null);

        nlog(['checkout_flow_submission' => $gatewayResponse, 'payment_id' => $paymentId]);

        if (!$paymentId) {
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

            // APM payments (SOFORT, Multibanco, etc.) may return Pending — create
            // the payment now and let the webhook update the status when settled.
            if (isset($payment['status']) && $payment['status'] === 'Pending') {
                return $this->processPendingPayment($payment);
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

    /**
     * Create a Payment Session for Flow SDK.
     */
    public function createPaymentSession(
        int $amount,
        string $reference,
        string $paymentHash,
        string $successUrl,
        string $failureUrl,
        bool $capture = true
    ): array {
        if ($this->checkout->gateway === null) {
            throw new \InvalidArgumentException('Checkout.com gateway is not configured. Please set the secret key.');
        }

        $customer = new PaymentCustomerRequest();
        $customer->email = $this->checkout->client->present()->email();
        $customer->name = $this->checkout->client->present()->name();

        $threeDs = new ThreeDsRequest();
        $threeDs->enabled = true;

        $billing = new BillingInformation();
        $billing->address = new Address();
        $billing->address->address_line1 = $this->checkout->client->address1 ?? '';
        $billing->address->address_line2 = $this->checkout->client->address2 ?? '';
        $billing->address->city = $this->checkout->client->city ?? '';
        $billing->address->state = $this->checkout->client->state ?? '';
        $billing->address->zip = $this->checkout->client->postal_code ?? '';
        $countryCode = $this->checkout->client->country?->iso_3166_2 ?? null;
        if (!$countryCode && $this->checkout->client->company->settings && isset($this->checkout->client->company->settings->country_id)) {
            $countryCode = \App\Models\Country::find($this->checkout->client->company->settings->country_id)?->iso_3166_2;
        }
        $billing->address->country = $countryCode ?? 'US';

        $request = new PaymentSessionsRequest();
        $request->amount = $amount;
        $request->currency = $this->checkout->client->getCurrencyCode();
        $request->reference = $reference;
        $request->processing_channel_id = $this->checkout->company_gateway->getConfigField('processingChannelId');
        $request->success_url = $successUrl;
        $request->failure_url = $failureUrl;
        $request->customer = $customer;
        $request->metadata = ['udf1' => 'Invoice Ninja', 'udf2' => $paymentHash];
        $request->three_ds = $threeDs;
        $request->capture = $capture;
        $request->billing = $billing;

        // Restrict the Flow widget to the active payment method.
        // Apple Pay and Google Pay must always be offered together as a pair.
        $flowMethod = self::gatewayTypeToFlowMethod($this->checkout->gateway_type_id);
        $walletGroup = ['applepay', 'googlepay'];

        if ($flowMethod && in_array($flowMethod, $walletGroup)) {
            $request->enabled_payment_methods = $walletGroup;
        } elseif ($flowMethod) {
            $request->enabled_payment_methods = [$flowMethod];
        }

        // Card-specific configuration (token storage)
        if ($this->checkout->gateway_type_id === GatewayType::CREDIT_CARD) {
            $cardConfig = new SessionCard();
            $cardConfig->store_payment_details = 'enabled';

            $methodConfig = new PaymentMethodConfiguration();
            $methodConfig->card = $cardConfig;
            $request->payment_method_configuration = $methodConfig;
        }

        $response = $this->checkout->gateway->getPaymentSessionsClient()->createPaymentSessions($request);

        nlog(['checkout_payment_session_response' => $response]);

        return $response;
    }
}
