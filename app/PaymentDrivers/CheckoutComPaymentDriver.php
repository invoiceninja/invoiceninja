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

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Http\Requests\Gateways\Checkout3ds\Checkout3dsRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\CheckoutCom\CheckoutWebhook;
use App\PaymentDrivers\CheckoutCom\CreditCard;
use App\PaymentDrivers\CheckoutCom\CreditCardFlow;
use App\PaymentDrivers\CheckoutCom\Utilities;
use App\Utils\Traits\SystemLogTrait;
use Checkout\CheckoutApiException;
use Checkout\CheckoutArgumentException;
use Checkout\CheckoutAuthorizationException;
use Checkout\CheckoutSdk;
use Checkout\Common\Phone;
use Checkout\Customers\CustomerRequest;
use Checkout\Environment;
use Checkout\Payments\Previous\PaymentRequest as PreviousPaymentRequest;
use Checkout\Payments\Previous\Source\RequestIdSource as SourceRequestIdSource;
use Checkout\Payments\RefundRequest;
use Checkout\Payments\Request\PaymentRequest;
use Checkout\Payments\Request\Source\RequestIdSource;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CheckoutComPaymentDriver extends BaseDriver
{
    use SystemLogTrait;
    use Utilities;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /* Gateway capabilities */
    public $refundable = true;

    /* Token billing */
    public $token_billing = true;

    /* Authorise payment methods */
    public $can_authorise_credit_card = true;

    public $is_four_api = false;

    /**
     * @var CheckoutSdk;
     */
    public $gateway;

    public $payment_method; //the gateway type id

    /** @var int Active GatewayType for this payment request. */
    public int $gateway_type_id = GatewayType::CREDIT_CARD;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    /** GatewayType IDs supported via the Flow SDK. */
    public static array $flow_gateway_types = [
        GatewayType::CREDIT_CARD,
        GatewayType::IDEAL,
        GatewayType::BANCONTACT,
        GatewayType::GIROPAY,
        GatewayType::EPS,
        GatewayType::SOFORT,
        GatewayType::PRZELEWY24,
        GatewayType::PAYPAL,
        GatewayType::APPLE_PAY,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHECKOUT;

    /**
     * Returns the gateway types available for this driver.
     *
     * When Flow SDK is active, returns only the types that the merchant's
     * Checkout.com account actually supports (probed and stored in settings).
     * Falls back to card-only if no probe has been run yet.
     */
    public function gatewayTypes(): array
    {
        $types = [GatewayType::CREDIT_CARD];

        if ($this->useFlow()) {
            $available = $this->company_gateway->settings->available_payment_methods ?? null;

            if (is_array($available) || is_object($available)) {
                $available = (array) $available;

                $types = array_filter(self::$flow_gateway_types, function (int $gt) use ($available) {
                    $method = self::gatewayTypeToFlowMethod($gt);
                    return $method && in_array($method, $available);
                });

                $types = array_values($types) ?: [GatewayType::CREDIT_CARD];
            }
        }

        return $types;
    }

    /**
     * Set the active payment method handler.
     *
     * When using the Flow SDK, all gateway types (card + APMs) route through
     * CreditCardFlow which restricts the Flow widget to the requested method.
     *
     * @param int|null $payment_method GatewayType constant
     * @return CheckoutComPaymentDriver
     */
    public function setPaymentMethod($payment_method = null): self
    {
        $this->gateway_type_id = (int) ($payment_method ?: GatewayType::CREDIT_CARD);

        if ($this->useFlow()) {
            $this->payment_method = new CreditCardFlow($this);
        } else {
            // Legacy Frames — only supports credit cards
            $this->payment_method = new CreditCard($this);
        }

        return $this;
    }

    /**
     * Initialize the checkout payment driver
     * @return $this
     */
    public function init()
    {
        $secretKey = $this->company_gateway->getConfigField('secretApiKey');
        if ($secretKey === null || $secretKey === '') {
            $this->gateway = null;
            return $this;
        }

        $publicKey = $this->company_gateway->getConfigField('publicApiKey');

        if (str_contains($secretKey, '-')) {

            $this->is_four_api = true; //was four api, now known as previous.

            /** @phpstan-ignore-next-line **/
            $builder = CheckoutSdk::builder()
                    ->previous()
                    ->staticKeys()
                    ->environment($this->company_gateway->getConfigField('testMode') ? Environment::sandbox() : Environment::production()) /** phpstan-ignore-line **/
                    ->publicKey($publicKey)
                    ->secretKey($secretKey);

            try {
                $this->gateway = $builder->build();
            } catch (CheckoutArgumentException $e) {
                // Public key format may not match the previous API pattern — retry without it.
                // The public key is only needed client-side (Frames/Flow JS), not for server-side operations.
                $builder = CheckoutSdk::builder()
                    ->previous()
                    ->staticKeys()
                    ->environment($this->company_gateway->getConfigField('testMode') ? Environment::sandbox() : Environment::production())
                    ->secretKey($secretKey);

                $this->gateway = $builder->build();
            }

        } else {

            /** @phpstan-ignore-next-line **/
            $builder = CheckoutSdk::builder()
                    ->staticKeys()
                    ->environment($this->company_gateway->getConfigField('testMode') ? Environment::sandbox() : Environment::production()) /** phpstan-ignore-line **/
                    ->publicKey($publicKey)
                    ->secretKey($secretKey);

            try {
                $this->gateway = $builder->build();
            } catch (CheckoutArgumentException $e) {
                // Public key format may not match the current API pattern — retry without it.
                // The public key is only needed client-side (Frames/Flow JS), not for server-side operations.
                $builder = CheckoutSdk::builder()
                    ->staticKeys()
                    ->environment($this->company_gateway->getConfigField('testMode') ? Environment::sandbox() : Environment::production())
                    ->secretKey($secretKey);

                $this->gateway = $builder->build();
            }

        }
        return $this;
    }

    /**
     * Process different view depending on payment type
     *
     * @param int $gateway_type_id The gateway type
     * @return string The view string
     */
    public function viewForType($gateway_type_id)
    {
        return 'gateways.checkout.credit_card.pay';
    }

    /**
     * Authorize View
     *
     * @param  array $data
     * @return \Illuminate\View\View
     */
    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    /**
     * Authorize Response
     *
     * @param  array $data
     * @return \Illuminate\View\View
     */
    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    /**
     * Payment View
     *
     * @param array $data Payment data array
     * @return \Illuminate\View\View
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Process the payment response
     *
     * @param \Illuminate\Http\Request $request The payment request
     * @return \Illuminate\View\View
     */
    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    /**
     * Store PaymentMethod
     *
     * @param  array $data
     * @return ?ClientGatewayToken $token
     */
    public function storePaymentMethod(array $data)
    {
        return $this->storeGatewayToken($data);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        if ($this->company_gateway->update_details) {
            $this->updateCustomer();
        }

        $request = new RefundRequest();
        $request->reference = "{$payment->transaction_reference} " . now();
        $request->amount = $this->convertToCheckoutAmount($amount, $this->client->getCurrencyCode());

        try {

            $response = $this->gateway->getPaymentsClient()->refundPayment($payment->transaction_reference, $request);


            SystemLogger::dispatch(
                array_merge(['message' => "Gateway Refund"], $response),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHECKOUT,
                $payment->client,
                $payment->company,
            );

            return [
                'transaction_reference' => $response['action_id'],
                'transaction_response' => json_encode($response),
                'success' => true,
                'description' => $response['reference'],
                'code' => 202,
            ];

        } catch (CheckoutApiException $e) {
            // API error
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        } catch (CheckoutArgumentException $e) {
            // Bad arguments

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $payment->client,
                $payment->company,
            );

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];

        } catch (CheckoutAuthorizationException $e) {

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $payment->client,
                $payment->company,
            );

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    public function getCustomer()
    {
        try {
            $response = $this->gateway->getCustomersClient()->get($this->client->present()->email());

            return $response;
        } catch (\Exception $e) {

            $request = new CustomerRequest();

            $phone = new Phone();
            $phone->number = substr(str_pad($this->client->present()->phone(), 6, "0", STR_PAD_RIGHT), 0, 24);
            $request->email = $this->client->present()->email();
            $request->name = $this->client->present()->name();
            $request->phone = $phone;

            try {
                $response = $this->gateway->getCustomersClient()->create($request);
            } catch (CheckoutApiException $e) {
                // API error
                $error_details = $e->error_details;

                if (isset($error_details['error_codes']) ?? false) {
                    $error_details = end($e->error_details['error_codes']);
                } else {
                    $error_details = $e->getMessage();
                }

                throw new PaymentFailed($error_details, 400);
            } catch (CheckoutArgumentException $e) {

                throw new PaymentFailed($e->getMessage(), $e->getCode());
            } catch (CheckoutAuthorizationException $e) {
                // Bad Invalid authorization

                throw new PaymentFailed("Checkout Gateway credentials are invalid", 400);
            }

            return $response;
        }
    }

    public function updateCustomer($customer_id = null)
    {

        if (!$customer_id) {
            return;
        }

        try {

            $request = new CustomerRequest();

            $phone = new Phone();
            $phone->number = substr(str_pad($this->client->present()->phone(), 6, "0", STR_PAD_RIGHT), 0, 24);
            $request->email = $this->client->present()->email();
            $request->name = $this->client->present()->name();
            $request->phone = $phone;

            $response = $this->gateway->getCustomersClient()->update($customer_id, $request);


        } catch (CheckoutApiException $e) {
            nlog($e->getMessage());
        } catch (CheckoutAuthorizationException $e) {
            nlog($e->getMessage());
        }

    }

    /**
     * Boots a request for a token payment
     *
     * @param  string $token
     * @return PreviousPaymentRequest | PaymentRequest
     */
    public function bootTokenRequest($token)
    {
        if ($this->is_four_api) {
            $token_source = new SourceRequestIdSource();
            $token_source->id = $token;
            $request = new PreviousPaymentRequest();
            $request->source = $token_source;
        } else {
            $token_source = new RequestIdSource();
            $token_source->id = $token;
            $request = new PaymentRequest();
            $request->source = $token_source;
        }

        return $request;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();
        $this->client = $invoice->client;
        $this->payment_hash = $payment_hash;

        $this->init();

        $paymentRequest = $this->bootTokenRequest($cgt->token);
        $paymentRequest->amount = $this->convertToCheckoutAmount($amount, $this->client->getCurrencyCode());
        $paymentRequest->reference = '#' . $invoice->number . ' - ' . now();
        $paymentRequest->customer = $this->getCustomer();
        $paymentRequest->metadata = ['udf1' => 'Invoice Ninja', 'udf2' => $payment_hash->hash];
        $paymentRequest->currency = $this->client->getCurrencyCode();

        $processingChannelId = $this->company_gateway->getConfigField('processingChannelId');
        if ($processingChannelId) {
            $paymentRequest->processing_channel_id = $processingChannelId;
        }

        // MIT recurring — exempt from 3DS challenge
        $paymentRequest->payment_type = 'Recurring';

        $request = new PaymentResponseRequest();
        $request->setMethod('POST');
        $request->request->add(['payment_hash' => $payment_hash->hash]);

        try {
            $response = $this->gateway->getPaymentsClient()->requestPayment($paymentRequest);

            if ($response['status'] == 'Authorized' || $response['status'] == 'Captured') {

                $meta = Utilities::resolvePaymentMeta($response);

                $data = [
                    'payment_method' => $response['source']['id'] ?? $response['id'],
                    'payment_type' => $meta['payment_type'],
                    'amount' => $amount,
                    'transaction_reference' => $response['id'],
                    'gateway_type_id' => $meta['gateway_type_id'],
                ];

                $this->confirmGatewayFee($data);

                $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

                SystemLogger::dispatch(
                    ['response' => $response, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_CHECKOUT,
                    $this->client,
                    $this->client->company,
                );

                return $payment;
            }

            if ($response['status'] == 'Declined') {
                $this->unWindGatewayFees($payment_hash);

                $this->sendFailureMail($response['status'] . ' ' . $response['response_summary']);

                $message = [
                    'server_response' => $response,
                    'data' => $payment_hash->data,
                ];

                SystemLogger::dispatch(
                    $message,
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_CHECKOUT,
                    $this->client,
                    $this->client->company
                );

                return false;
            }
        } catch (CheckoutApiException $e) {

            $this->unWindGatewayFees($payment_hash);

            $error_details = $e->error_details;

            if (isset($error_details['error_codes']) ?? false) {
                $error_details = end($e->error_details['error_codes']);
            } else {
                $error_details = $e->getMessage();
            }

            $data = [
                'status' => $e->error_details,
                'error_type' => '',
                'error_code' => $e->getCode(),
                'param' => '',
                'message' => $e->getMessage(),
            ];

            $this->sendFailureMail($e->getMessage());

            SystemLogger::dispatch(
                $data,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $this->client,
                $this->client->company
            );
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {

        header('Content-Type: text/plain');
        $webhook_payload = file_get_contents('php://input');

        if ($request->header('cko-signature') == hash_hmac('sha256', $webhook_payload, $this->company_gateway->company->company_key)) {
            CheckoutWebhook::dispatch($request->all(), $request->company_key, $this->company_gateway->id)->delay(10);
        } else {
            nlog("Hash Mismatch = {$request->header('cko-signature')} " . hash_hmac('sha256', $webhook_payload, $this->company_gateway->company->company_key));
            nlog($request->all());
        }

        return response()->json(['success' => true]);
    }

    public function process3dsConfirmation(Checkout3dsRequest $request)
    {
        $this->init();
        $this->setPaymentHash($request->getPaymentHash());

        //11-08-2022 check the user is authenticated
        if (!Auth::guard('contact')->check()) {
            $client = $request->getClient();
            $this->client = $client;
            auth()->guard('contact')->loginUsingId($client->contacts()->first()->id, true);
        }

        try {
            $payment = $this->gateway->getPaymentsClient()->getPaymentDetails(
                $request->query('cko-session-id')
            );

            nlog("checkout3ds");
            nlog($payment);

            if (isset($payment['approved']) && $payment['approved']) {
                return $this->processSuccessfulPayment($payment);
            } else {
                return $this->processUnsuccessfulPayment($payment);
            }
        } catch (CheckoutApiException|Exception $e) {
            nlog("checkout");
            nlog($e->getMessage());
            return $this->processInternallyFailedPayment($this, $e);
        }
    }

    public function detach(ClientGatewayToken $clientGatewayToken)
    {
        // Gateway doesn't support this feature.
    }

    public function auth(): string
    {
        try {
            $this->init()->gateway->getCustomersClient('x');
            return 'ok';
        } catch (\Exception $e) {

        }
        return 'error';
    }

    private function getToken(string $token, $gateway_customer_reference)
    {
        return  ClientGatewayToken::query()
                                  ->where('company_id', $this->company_gateway->company_id)
                                  ->where('gateway_customer_reference', $gateway_customer_reference)
                                  ->where('token', $token)
                                  ->first();
    }

    /**
     * ImportCustomers
     *
     * Only their methods because checkout.com
     * does not have a list route for customers
     *
     * @return void
     */
    public function importCustomers()
    {
        $this->init();

        $this->company_gateway
             ->company
             ->clients()
             ->cursor()
             ->each(function ($client) {

                 if (!str_contains($client->present()->email(), "@")) {
                     return;
                 }

                 try {
                     $customer = $this->gateway->getCustomersClient()->get($client->present()->email());
                 } catch (\Exception $e) {
                     nlog("Checkout: Customer not found");
                     return;
                 }

                 $this->client = $client;

                 nlog($customer['instruments']);

                 foreach ($customer['instruments'] as $card) {
                     if (
                         $card['type'] != 'card'
                         || Carbon::createFromDate($card['expiry_year'], $card['expiry_month'], '1')->lt(now()) //@phpstan-ignore-line
                         || $this->getToken($card['id'], $customer['id'])
                     ) {
                         continue;
                     }

                     $payment_meta = new \stdClass();
                     $payment_meta->exp_month = (string) $card['expiry_month'];
                     $payment_meta->exp_year = (string) $card['expiry_year'];
                     $payment_meta->brand = (string) $card['scheme'];
                     $payment_meta->last4 = (string) $card['last4'];
                     $payment_meta->type = (int) GatewayType::CREDIT_CARD;

                     $data = [
                         'payment_meta' => $payment_meta,
                         'token' => $card['id'],
                         'payment_method_id' => GatewayType::CREDIT_CARD,
                     ];

                     $this->storeGatewayToken($data, ['gateway_customer_reference' => $customer['id']]);

                 }

             });
    }

    public function livewirePaymentView(array $data): string
    {
        return $this->payment_method->livewirePaymentView($data);
    }

    /**
     * Whether to use Checkout.com Flow SDK (payment sessions) instead of Frames.
     * True when on current API and processingChannelId is configured.
     */
    public function useFlow(): bool
    {
        $secretKey = $this->company_gateway->getConfigField('secretApiKey');
        if (empty($secretKey) || str_contains($secretKey, '-')) {
            return false;
        }

        $channelId = $this->company_gateway->getConfigField('processingChannelId');

        return $channelId !== null && $channelId !== '';
    }

    /**
     * Probe the Checkout.com account to discover which payment methods
     * are available for the configured processing channel.
     *
     * Uses the GET /payment-methods endpoint with ?processing_channel_id
     * to retrieve the list. Stores the result in company_gateway->settings
     * so that gatewayTypes() only advertises supported methods.
     *
     * @see https://api-reference.checkout.com/#operation/getPaymentMethods
     */
    public function probeAvailablePaymentMethods(): array
    {
        $this->init();

        if (!$this->useFlow()) {
            return ['card'];
        }

        $secretKey = $this->company_gateway->getConfigField('secretApiKey');
        $processingChannelId = $this->company_gateway->getConfigField('processingChannelId');
        $testMode = $this->company_gateway->getConfigField('testMode');

        $baseUrl = $testMode ? 'https://api.sandbox.checkout.com' : 'https://api.checkout.com';
        $url = $baseUrl . '/payment-methods?' . http_build_query(['processing_channel_id' => $processingChannelId]);

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/json',
            ])->get($url);

            if (!$response->successful()) {
                nlog("Checkout probe: GET /payment-methods returned {$response->status()}");
                nlog($response->body());
                return $this->persistAvailableMethods(['card']);
            }

            $body = $response->json();
            nlog(['checkout_payment_methods_response' => $body]);

            $methods = [];

            // Normalize: Checkout.com returns "card_scheme" per brand (Visa, MC, etc.)
            // and APMs by their type (ideal, giropay, etc.)
            $typeMap = [
                'card_scheme' => 'card',
                'applepay'    => 'applepay',
                'googlepay'   => 'googlepay',
            ];

            foreach ($body['methods'] ?? [] as $method) {
                if (!isset($method['type'])) {
                    continue;
                }

                $type = strtolower($method['type']);
                $normalized = $typeMap[$type] ?? $type;

                if (!in_array($normalized, $methods)) {
                    $methods[] = $normalized;
                }
            }

            // Apple Pay and Google Pay are card-based wallet methods that the
            // Flow SDK enables automatically when card is available and the
            // device supports them. They won't appear in /payment-methods
            // but should be allowed when card payments are supported.
            if (in_array('card', $methods)) {
                if (!in_array('applepay', $methods)) {
                    $methods[] = 'applepay';
                }
                if (!in_array('googlepay', $methods)) {
                    $methods[] = 'googlepay';
                }
            }

            $available = $methods ?: ['card'];

        } catch (\Exception $e) {
            nlog("Checkout probe failed: " . $e->getMessage());
            $available = ['card'];
        }

        return $this->persistAvailableMethods($available);
    }

    /**
     * Store the probed payment methods in the CompanyGateway settings.
     */
    private function persistAvailableMethods(array $available): array
    {
        $settings = $this->company_gateway->settings ?? new \stdClass();
        $settings->available_payment_methods = $available;
        $this->company_gateway->settings = $settings;
        $this->company_gateway->save();

        nlog(['checkout_probed_payment_methods' => $available]);

        return $available;
    }
}
