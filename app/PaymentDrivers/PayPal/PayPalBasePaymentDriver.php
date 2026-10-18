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

namespace App\PaymentDrivers\PayPal;

use Str;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\BaseDriver;
use Illuminate\Support\Facades\Http;
use App\PaymentDrivers\PayPal\PayPalWebhook;

class PayPalBasePaymentDriver extends BaseDriver
{
    use MakesHash;

    public string $risk_guid;

    public $token_billing = true;

    public $can_authorise_credit_card = false;

    public float $fee = 0;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public string $api_endpoint_url = '';

    public string $paypal_payment_method = '';

    public ?int $gateway_type_id = null;

    public mixed $access_token = null;

    public ?Carbon $token_expiry = null;

    public array $funding_options = [
        3 => 'paypal',
        1 => 'card',
        25 => 'venmo',
        29 => 'paypal_advanced_cards',
        // 9 => 'sepa',
        // 12 => 'bancontact',
        // 17 => 'eps',
        // 15 => 'giropay',
        // 13 => 'ideal',
        // 26 => 'mercadopago',
        // 27 => 'mybank',
        28 => 'paylater',
        // 16 => 'p24',
        // 7 => 'sofort'
    ];

    public function gatewayTypes()
    {

        $funding_options

        = collect($this->company_gateway->fees_and_limits)
            ->filter(function ($fee) {
                return $fee->is_enabled;
            })->map(function ($fee, $key) {
                return (int) $key;
            })->toArray();

        /** Parse funding options and remove card option if advanced cards is enabled. */
        if (in_array(1, $funding_options) && in_array(29, $funding_options)) {

            if (($key = array_search(1, $funding_options)) !== false) {
                unset($funding_options[$key]);
            }

        }

        return $funding_options;

    }

    public function getPaymentMethod($gateway_type_id): int
    {
        $method = PaymentType::PAYPAL;

        match ($gateway_type_id) {
            "1" => $method = PaymentType::CREDIT_CARD_OTHER,
            "3" => $method = PaymentType::PAYPAL,
            "25" => $method = PaymentType::VENMO,
            "28" => $method = PaymentType::PAY_LATER,
            "29" => $method = PaymentType::CREDIT_CARD_OTHER,
            default => $method = PaymentType::PAYPAL,
        };

        return $method;
    }

    /**
     * @param  mixed  $request
     * @param  array<string, mixed>|null  $response
     */
    protected function resolveGatewayTypeId($request, ?array $response = null): int
    {
        $from_request = $request->input('gateway_type_id') ?? $request->input('payment_method_id');

        if ($from_request) {
            return (int) $from_request;
        }

        if ($this->gateway_type_id) {
            return (int) $this->gateway_type_id;
        }

        if ($response !== null && isset($response['payment_source']) && is_array($response['payment_source'])) {
            $source = array_key_first($response['payment_source']);

            if (is_string($source)) {
                return $this->mapPaymentSourceToGatewayType($source);
            }
        }

        return GatewayType::PAYPAL;
    }

    protected function mapPaymentSourceToGatewayType(string $source): int
    {
        return match ($source) {
            'venmo' => GatewayType::VENMO,
            'paylater' => GatewayType::PAYLATER,
            'card' => $this->resolveCardGatewayTypeId(),
            default => GatewayType::PAYPAL,
        };
    }

    protected function resolveCardGatewayTypeId(): int
    {
        if (in_array((int) $this->gateway_type_id, [GatewayType::CREDIT_CARD, GatewayType::PAYPAL_ADVANCED_CARDS], true)) {
            return (int) $this->gateway_type_id;
        }

        $limits = $this->company_gateway->fees_and_limits ?? null;

        if (
            is_object($limits)
            && property_exists($limits, (string) GatewayType::PAYPAL_ADVANCED_CARDS)
            && $limits->{GatewayType::PAYPAL_ADVANCED_CARDS}->is_enabled
        ) {
            return GatewayType::PAYPAL_ADVANCED_CARDS;
        }

        return GatewayType::CREDIT_CARD;
    }

    public function gatewayTypeFromPaymentSource(string $source): int
    {
        return $this->mapPaymentSourceToGatewayType($source);
    }

    public function init()
    {
        $this->risk_guid = Str::random(32);

        $this->api_endpoint_url = $this->company_gateway->getConfigField('testMode') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        if (\App\Utils\Ninja::isHosted() && $this->company_gateway->gateway_key != '80af24a6a691230bbec33e930ab40665') {
            $secret = config('ninja.paypal.secret');
            $client_id = config('ninja.paypal.client_id');
        } else {

            $secret = $this->company_gateway->getConfigField('secret');
            $client_id = $this->company_gateway->getConfigField('clientId');
        }

        if ($this->access_token && $this->token_expiry && $this->token_expiry->isFuture()) {
            return $this;
        }

        $response = Http::withBasicAuth($client_id, $secret)
                                    ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                                    ->withQueryParameters(['grant_type' => 'client_credentials'])
                                    ->post("{$this->api_endpoint_url}/v1/oauth2/token");

        if ($response->successful()) {
            $this->access_token = $response->json()['access_token'];
            $this->token_expiry = now()->addSeconds($response->json()['expires_in'] - 60);
        } else {
            throw new PaymentFailed('Unable to gain access token from Paypal. Check your configuration', 401);
        }

        return $this;

    }


    /**
     * getFundingOptions
     *
     * Hosted fields requires this.
     *
     * @return string
     */
    public function getFundingOptions(): string
    {

        $enums = [
            1 => 'card',
            3 => 'paypal',
            25 => 'venmo',
            28 => 'paylater',
            // 9 => 'sepa',
            // 12 => 'bancontact',
            // 17 => 'eps',
            // 15 => 'giropay',
            // 13 => 'ideal',
            // 26 => 'mercadopago',
            // 27 => 'mybank',
            // 28 => 'paylater',
            // 16 => 'p24',
            // 7 => 'sofort'
        ];

        $funding_options = '';

        foreach ($this->company_gateway->fees_and_limits as $key => $value) {

            if ($value->is_enabled) {

                $funding_options .= $enums[$key] . ',';

            }

        }

        return rtrim($funding_options, ',');

    }

    //@todo turn this back on when PayPal.....
    public function getClientHash()
    {
        return '';

        /** @var ?\App\Models\ClientGatewayToken $cgt */
        $cgt = ClientGatewayToken::where('company_gateway_id', $this->company_gateway->id)
                                 ->where('client_id', $this->client->id)
                                 ->first();
        if (!$cgt) {
            return '';
        }

        $client_reference = $cgt->gateway_customer_reference;

        $secret = $this->company_gateway->getConfigField('secret');
        $client_id = $this->company_gateway->getConfigField('clientId');

        $response = Http::withBasicAuth($client_id, $secret)
                                   ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                                   ->withQueryParameters(['grant_type' => 'client_credentials','response_type' => 'id_token', 'target_customer_id' => $client_reference])
                                   ->post("{$this->api_endpoint_url}/v1/oauth2/token");

        if ($response->successful()) {

            $data = $response->json();

            return $data['id_token'] ?? '';

        }

        return '';
    }

    protected function formatPayPalAmount(float|string $amount): string
    {
        $precision = $this->client->currency()->precision;

        return number_format((float) $amount, $precision, '.', '');
    }

    /**
     * Build the PayPal invoice_id sent to buyers (transaction history / emails).
     *
     * Uses the clean Ninja invoice number by default. On duplicate collisions only,
     * appends a numeric suffix (-2, -3, …). Internal uniqueness stays on custom_id.
     */
    protected function getPayPalInvoiceId(Invoice $invoice, int $attempt = 1): string
    {
        if ($attempt <= 1) {
            return $invoice->number;
        }

        return $invoice->number . '-' . $attempt;
    }

    protected function isDuplicateInvoiceError(\Illuminate\Http\Client\Response|array|null $response): bool
    {
        if ($response === null) {
            return false;
        }

        $body = $response instanceof \Illuminate\Http\Client\Response ? ($response->json() ?? []) : $response;

        if (! is_array($body)) {
            return false;
        }

        foreach ($body['details'] ?? [] as $detail) {
            if (($detail['issue'] ?? '') === 'DUPLICATE_INVOICE_ID') {
                return true;
            }
        }

        return stripos(json_encode($body), 'DUPLICATE_INVOICE_ID') !== false;
    }

    protected function extractPayPalErrorMessage(\Illuminate\Http\Client\Response|array $response): string
    {
        $body = $response instanceof \Illuminate\Http\Client\Response ? ($response->json() ?? []) : $response;

        if (! is_array($body)) {
            return 'Payment failed. Please try again.';
        }

        return $body['details'][0]['description']
            ?? $body['message']
            ?? $body['error_description']
            ?? 'Payment failed. Please try again.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseToArray(mixed $response): array
    {
        if ($response instanceof \Illuminate\Http\Client\Response) {
            return $response->json() ?? [];
        }

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response->getData(true);
        }

        return is_array($response) ? $response : [];
    }

    protected function beforeCheckout(): void
    {
    }

    protected function resolveSdkClientId(): string
    {
        return $this->company_gateway->getConfigField('clientId');
    }

    protected function getCheckoutIdentifier(): string
    {
        return 's:INN_ACDC_CHCK';
    }

    /**
     * @param  array<string, mixed>  $unit
     */
    protected function enrichPurchaseUnit(array &$unit): void
    {
    }

    public function handleDuplicateInvoiceId(string $orderID, int $attempt = 1): \Illuminate\Http\Client\Response
    {
        $_invoice = collect($this->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));
        $next_attempt = $attempt + 1;
        $new_invoice_number = $this->getPayPalInvoiceId($invoice, $next_attempt);

        $update_data = [[
            "op" => "replace",
            "path" => "/purchase_units/@reference_id=='default'/invoice_id",
            "value" => $new_invoice_number,
        ]];

        $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $update_data);
        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

        if ($r->status() == 422 && $this->isDuplicateInvoiceError($r) && $next_attempt < 3) {
            return $this->handleDuplicateInvoiceId($orderID, $next_attempt);
        }

        return $r;
    }

    public function getShippingAddress(): ?array
    {
        return $this->company_gateway->require_shipping_address
        ? [
            "address"
                => [
                    "address_line_1" => strlen($this->client->shipping_address1 ?? '') > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => strlen($this->client->shipping_city ?? '') > 1 ? $this->client->shipping_city : $this->client->city,
                    "admin_area_1" => strlen($this->client->shipping_state ?? '') > 1 ? $this->client->shipping_state : $this->client->state,
                    "postal_code" => strlen($this->client->shipping_postal_code ?? '') > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
                ],
        ]

        : [
            "name" => [
                "full_name" => $this->client->present()->name(),
            ],
        ];

    }

    public function getBillingAddress(): array
    {
        return
            [
                "address_line_1" => $this->client->address1,
                "address_line_2" => $this->client->address2,
                "admin_area_2" => $this->client->city,
                "admin_area_1" => $this->client->state,
                "postal_code" => $this->client->postal_code,
                "country_code" => $this->client->country->iso_3166_2,
            ];
    }

    public function getPaymentSource(): array
    {
        //@todo - roll back here for advanced payments vs hosted card fields.
        if ($this->gateway_type_id == GatewayType::PAYPAL_ADVANCED_CARDS) {

            return [
                "card" => [
                    "attributes" => [
                        "verification" => [
                            "method" => "SCA_WHEN_REQUIRED", //SCA_ALWAYS
                            // "method" => "SCA_ALWAYS", //SCA_ALWAYS
                        ],
                        "vault" => [
                            "store_in_vault" => "ON_SUCCESS", //must listen to this webhook - VAULT.PAYMENT-TOKEN.CREATED webhook.
                        ],
                    ],
                    "experience_context" => [
                        "shipping_preference" => "SET_PROVIDED_ADDRESS",
                    ],
                    "stored_credential" => [
                        // "payment_initiator" => "MERCHANT", //"CUSTOMER" who initiated the transaction?
                        "payment_initiator" => "CUSTOMER", //"" who initiated the transaction?
                        "payment_type" => "UNSCHEDULED", //UNSCHEDULED
                        "usage" => "DERIVED",
                    ],
                ],
            ];

        }

        $order = [
            "paypal" => [
                "name" => [
                    "given_name" => $this->client->present()->first_name(),
                    "surname" => $this->client->present()->last_name(),
                ],
                "email_address" => $this->client->present()->email(),
                "experience_context" => [
                    "user_action" => "PAY_NOW",
                ],
            ],
        ];

        /** If we have a complete address, add it to the order, otherwise leave it blank! */
        if (
            strlen($this->client->shipping_address1 ?? '') > 2
           && strlen($this->client->shipping_city ?? '') > 2
           && strlen($this->client->shipping_state ?? '') >= 2
           && strlen($this->client->shipping_postal_code ?? '') > 2
           && strlen($this->client->shipping_country->iso_3166_2 ?? '') >= 2
        ) {
            $order['paypal']['address'] = [
                "address_line_1" => $this->client->shipping_address1,
                "address_line_2" => $this->client->shipping_address2,
                "admin_area_2" => $this->client->shipping_city,
                "admin_area_1" => $this->client->shipping_state,
                "postal_code" => $this->client->shipping_postal_code,
                "country_code" => $this->client->present()->shipping_country_code(),
            ];
        } elseif (
            strlen($this->client->address1 ?? '') > 2
           && strlen($this->client->city ?? '') > 2
           && strlen($this->client->state ?? '') >= 2
           && strlen($this->client->postal_code ?? '') > 2
           && strlen($this->client->country->iso_3166_2 ?? '') >= 2
        ) {
            $order['paypal']['address'] = [
                "address_line_1" => $this->client->address1,
                "address_line_2" => $this->client->address2,
                "admin_area_2" => $this->client->city,
                "admin_area_1" => $this->client->state,
                "postal_code" => $this->client->postal_code,
                "country_code" => $this->client->country->iso_3166_2,
            ];
        }

        return $order;

    }

    /**
     * Payment method setter
     *
     * @param  mixed $payment_method_id
     * @return self
     */
    public function setPaymentMethod($payment_method_id): self
    {
        if (!$payment_method_id) {
            return $this;
        }

        $this->gateway_type_id = $payment_method_id;

        $this->paypal_payment_method = $this->funding_options[$payment_method_id];

        return $this;
    }

    public function authorizeView($payment_method)
    {
        // PayPal doesn't support direct authorization.

        return $this;
    }

    public function authorizeResponse($request)
    {
        // PayPal doesn't support direct authorization.

        return $this;
    }

    /**
     * Generates the gateway request
     *
     * @param  string $uri
     * @param  string $verb
     * @param  array $data
     * @param  ?array $headers
     * @return \Illuminate\Http\Client\Response
     */
    public function gatewayRequest(string $uri, string $verb, array $data, ?array $headers = [])
    {
        $this->init();

        $r = Http::withToken($this->access_token)
                ->withHeaders($this->getHeaders($headers))
                ->{$verb}("{$this->api_endpoint_url}{$uri}", $data);

        if ($r->status() <= 422) {
            // if($r->successful()) {
            return $r;
        }

        nlog($r->json());

        SystemLogger::dispatch(
            ['response' => $r->body()],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            static::SYSTEM_LOG_TYPE,
            $this->client,
            $this->client->company ?? $this->company_gateway->company,
        );


        return $r;

        // throw new PaymentFailed("Gateway failure - {$r->body()}", 401);

    }

    public function handleProcessingFailure(array $response)
    {

        SystemLogger::dispatch(
            ['response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            static::SYSTEM_LOG_TYPE,
            $this->client,
            $this->client->company ?? $this->company_gateway->company,
        );

        switch ($response['name']) {
            case 'NOT_AUTHORIZED':
                throw new PaymentFailed("There was a permissions issue processing this payment, please contact the merchant. ", 401);


            default:
                throw new PaymentFailed("Unknown error occurred processing payment. Please contact merchant.", 500);

        }
    }

    public function handleRetry($response, $request)
    {
        return response()->json($response->json());
    }

    /**
     * Generates the request headers
     *
     * @param  array $headers
     * @return array
     */
    public function getHeaders(array $headers = []): array
    {

        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Accept-Language' => 'en_US',
            'PayPal-Partner-Attribution-Id' => 'invoiceninja_SP_PPCP',
            'PayPal-Request-Id' => Str::uuid()->toString(),
            'PAYPAL-CLIENT-METADATA-ID' => $this->risk_guid,
        ], $headers);
    }

    /**
     * Generates a client token for the payment form.
     *
     * @return string
     */
    public function getClientToken(): string
    {

        $r = $this->gatewayRequest('/v1/identity/generate-token', 'post', ['body' => '']);

        if ($r->successful()) {
            return $r->json()['client_token'];
        }

        throw new PaymentFailed('Unable to gain client token from Paypal. Check your configuration', 401);

    }

    public function auth(): string
    {

        try {
            $this->init()->getClientToken();
            return 'ok';
        } catch (\Exception $e) {

        }

        return 'error';
    }

    public function importCustomers()
    {
        return true;
    }

    public function processWebhookRequest(Request $request)
    {

        $this->init();

        PayPalWebhook::dispatch($request->all(), $request->headers->all(), $this->access_token);

    }

    public function createNinjaPayment($request, $response)
    {

        if (isset($response['purchase_units'][0]['payments']['captures'][0]['status']) && in_array($response['purchase_units'][0]['payments']['captures'][0]['status'], ['COMPLETED', 'PENDING'])) {

            $payment_status = $response['purchase_units'][0]['payments']['captures'][0]['status'] == 'COMPLETED' ? \App\Models\Payment::STATUS_COMPLETED : \App\Models\Payment::STATUS_PENDING;

            $response_array = $this->responseToArray($response);
            $gateway_type_id = $this->resolveGatewayTypeId($request, $response_array);

            $data = [
                'payment_type' => $this->getPaymentMethod((string) $gateway_type_id),
                'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => $gateway_type_id,
            ];

            $payment = $this->createPayment($data, $payment_status);

            if ($request->has('store_card') && $request->input('store_card') === true) {
                $payment_source = $response_array['payment_source'] ?? false;

                if (isset($payment_source['card']) && ($payment_source['card']['attributes']['vault']['status'] ?? false) && $payment_source['card']['attributes']['vault']['status'] == 'VAULTED') {

                    $last4 = $payment_source['card']['last_digits'];
                    $expiry = $payment_source['card']['expiry']; //'2025-01'
                    $expiry_meta = explode('-', $expiry);
                    $brand = $payment_source['card']['brand'];

                    $payment_meta = new \stdClass();
                    $payment_meta->exp_month = $expiry_meta[1] ?? '';
                    $payment_meta->exp_year = $expiry_meta[0] ?? $expiry;
                    $payment_meta->brand = $brand;
                    $payment_meta->last4 = $last4;
                    $payment_meta->type = GatewayType::CREDIT_CARD;

                    $token = $payment_source['card']['attributes']['vault']['id']; // 09f28652d01257021
                    $gateway_customer_reference = $payment_source['card']['attributes']['vault']['customer']['id']; //rbTHnLsZqE;

                    $data['token'] = $token;
                    $data['payment_method_id'] = $gateway_type_id;
                    $data['payment_meta'] = $payment_meta;

                    $additional['gateway_customer_reference'] = $gateway_customer_reference;

                    $this->storeGatewayToken($data, $additional);

                }
            }

            SystemLogger::dispatch(
                ['response' => $this->responseToArray($response), 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                static::SYSTEM_LOG_TYPE,
                $this->client,
                $this->client->company,
            );

            return response()->json(['redirect' => route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)], false)]);
        }

        SystemLogger::dispatch($response, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, static::SYSTEM_LOG_TYPE, $this->client, $this->client->company);

        $response_array = $this->responseToArray($response);
        $error = $response_array['purchase_units'][0]['payments']['captures'][0]['status_details'][0]
            ?? $response_array['purchase_units'][0]['payments']['captures'][0]['status']
            ?? $this->extractPayPalErrorMessage($response_array);

        return response()->json(['message' => $error], 400);

    }

    public function getOrder(string $order_id): array
    {
        $this->init();

        $r = $this->gatewayRequest("/v2/checkout/orders/{$order_id}", 'get', ['body' => '']);

        return $r->json();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createOrder(array $data): string
    {
        $_invoice = collect($this->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $description = collect($invoice->line_items)->map(function ($item) {
            return $item->notes;
        })->implode("\n");

        $amount = $this->formatPayPalAmount($data['amount_with_fee']);

        $purchase_unit = [
            "custom_id" => $this->payment_hash->hash,
            "description" => ctrans('texts.invoice_number') . '# ' . $invoice->number,
            "invoice_id" => $this->getPayPalInvoiceId($invoice),
            "amount" => [
                "value" => $amount,
                "currency_code" => $this->client->currency()->code,
                "breakdown" => [
                    "item_total" => [
                        "currency_code" => $this->client->currency()->code,
                        "value" => $amount,
                    ],
                ],
            ],
            "items" => [
                [
                    "name" => ctrans('texts.invoice_number') . '# ' . $invoice->number,
                    "description" => mb_substr($description, 0, 127),
                    "quantity" => "1",
                    "unit_amount" => [
                        "currency_code" => $this->client->currency()->code,
                        "value" => $amount,
                    ],
                ],
            ],
        ];

        $this->enrichPurchaseUnit($purchase_unit);

        $order = [
            "intent" => "CAPTURE",
            "payment_source" => $this->getPaymentSource(),
            "purchase_units" => [$purchase_unit],
        ];

        if ($shipping = $this->getShippingAddress()) {
            $order['purchase_units'][0]["shipping"] = $shipping;
        }

        if (isset($data['payment_source'])) {
            $order['payment_source'] = $data['payment_source'];
        }

        if (isset($data['payer'])) {
            $order['payer'] = $data['payer'];
        }

        $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);
        $attempt = 1;

        while ($r->status() == 422 && $this->isDuplicateInvoiceError($r) && $attempt < 3) {
            $attempt++;
            $order['purchase_units'][0]['invoice_id'] = $this->getPayPalInvoiceId($invoice, $attempt);
            $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);
        }

        $response = $r->json();

        if (! isset($response['id'])) {
            $this->handleProcessingFailure($response ?? ['name' => '']);
        }

        $this->payment_hash->withData("orderID", $response['id']);

        return $response['id'];
    }

    /**
     * @param  mixed  $request
     */
    public function processPaymentResponse($request)
    {
        $this->init();

        $request['gateway_response'] = str_replace("Error: ", "", $request['gateway_response']);
        $response = json_decode($request['gateway_response'], true);

        if ($request->has('token') && strlen($request->input('token', '')) > 2) {
            return $this->processTokenPayment($request, $response);
        }

        $orderID = $response['orderID'] ?? $this->payment_hash->data->orderID;

        if ($this->company_gateway->require_shipping_address) {
            $shipping_data = [[
                "op" => "replace",
                "path" => "/purchase_units/@reference_id=='default'/shipping/address",
                "value" => [
                    "address_line_1" => strlen($this->client->shipping_address1 ?? '') > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => strlen($this->client->shipping_city ?? '') > 1 ? $this->client->shipping_city : $this->client->city,
                    "admin_area_1" => strlen($this->client->shipping_state ?? '') > 1 ? $this->client->shipping_state : $this->client->state,
                    "postal_code" => strlen($this->client->shipping_postal_code ?? '') > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
                ],
            ]];

            $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $shipping_data);
        }

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

        if ($r->status() == 422 && $this->isDuplicateInvoiceError($r)) {
            $r = $this->handleDuplicateInvoiceId($orderID);
        }

        $response_array = $this->responseToArray($r);

        if (isset($response_array['status']) && $response_array['status'] == 'COMPLETED' && isset($response_array['purchase_units'])) {
            return $this->createNinjaPayment($request, $response_array);
        }

        SystemLogger::dispatch(
            ['response' => $response_array],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            static::SYSTEM_LOG_TYPE,
            $this->client,
            $this->client->company,
        );

        return response()->json(['message' => $this->extractPayPalErrorMessage($response_array)], 400);
    }

    /**
     * @param  mixed  $request
     * @param  array<string, mixed>  $response
     */
    public function processTokenPayment($request, array $response)
    {
        $cgt = ClientGatewayToken::where('client_id', $this->client->id)
                                 ->where('token', $request['token'])
                                 ->firstOrFail();

        $orderId = $response['orderID'];

        $this->gatewayRequest("/v2/checkout/orders/{$orderId}", 'delete', ['body' => '']);

        $data["payer"] = [
            "name" => [
                "given_name" => $this->client->present()->first_name(),
                "surname" => $this->client->present()->last_name(),
            ],
            "email_address" => $this->client->present()->email(),
        ];

        $data['amount_with_fee'] = $this->payment_hash->data->amount_with_fee;
        $data["payment_source"] = [
            "card" => [
                "vault_id" => $cgt->token,
                "stored_credential" => [
                    "payment_initiator" => "MERCHANT",
                    "payment_type" => "UNSCHEDULED",
                    "usage" => "SUBSEQUENT",
                ],
            ],
        ];

        $orderId = $this->createOrder($data);

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderId}", 'get', ['body' => '']);

        if ($r->status() == 422 && $this->isDuplicateInvoiceError($r)) {
            $r = $this->handleDuplicateInvoiceId($orderId);
        }

        $response_array = $r->json();

        if (isset($response_array['purchase_units'][0]['payments']['captures'][0]['status']) && $response_array['purchase_units'][0]['payments']['captures'][0]['status'] == 'COMPLETED') {
            $gateway_type_id = (int) ($cgt->gateway_type_id ?: $this->resolveGatewayTypeId($request));

            $data = [
                'payment_type' => $this->getPaymentMethod((string) $gateway_type_id),
                'amount' => $response_array['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response_array['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => $gateway_type_id,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response_array, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                static::SYSTEM_LOG_TYPE,
                $this->client,
                $this->client->company,
            );

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
        }

        return response()->json(['message' => $this->extractPayPalErrorMessage($response_array)], 400);
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->payment_hash = $payment_hash;

        $data = [
            "payer" => [
                "name" => [
                    "given_name" => $this->client->present()->first_name(),
                    "surname" => $this->client->present()->last_name(),
                ],
                "email_address" => $this->client->present()->email(),
            ],
            'amount_with_fee' => $this->payment_hash->data->amount_with_fee,
            "payment_source" => [
                "card" => [
                    "vault_id" => $cgt->token,
                    "stored_credential" => [
                        "payment_initiator" => "MERCHANT",
                        "payment_type" => "UNSCHEDULED",
                        "usage" => "SUBSEQUENT",
                    ],
                ],
            ],
        ];

        $orderId = $this->createOrder($data);

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderId}", 'get', ['body' => '']);

        if ($r->status() == 422 && $this->isDuplicateInvoiceError($r)) {
            $r = $this->handleDuplicateInvoiceId($orderId);
        }

        $response_array = $r->json();

        if (isset($response_array['purchase_units'][0]['payments']['captures'][0]['status']) && $response_array['purchase_units'][0]['payments']['captures'][0]['status'] == 'COMPLETED') {
            $gateway_type_id = (int) $cgt->gateway_type_id;

            $data = [
                'payment_type' => $this->getPaymentMethod((string) $gateway_type_id),
                'amount' => $response_array['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response_array['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => $gateway_type_id,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response_array, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                static::SYSTEM_LOG_TYPE,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        $this->processInternallyFailedPayment($this, new \Exception('Auto billing failed.', 400));

        SystemLogger::dispatch($response_array, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, static::SYSTEM_LOG_TYPE, $this->client, $this->client->company);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function processPaymentViewData(array $data): array
    {
        $this->init();
        $this->beforeCheckout();

        $data['gateway'] = $this;
        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $data['client_id'] = $this->resolveSdkClientId();
        $data['token'] = $this->getClientToken();
        $data['order_id'] = $this->createOrder($data);
        $data['funding_source'] = $this->paypal_payment_method;
        $data['gateway_type_id'] = $this->gateway_type_id;
        $data['currency'] = $this->client->currency()->code;
        $data['guid'] = $this->risk_guid;
        $data['identifier'] = $this->getCheckoutIdentifier();
        $data['pp_client_reference'] = $this->getClientHash();
        $data['invoice_hash'] = $this->payment_hash->fee_invoice->hashed_id;

        return $data;
    }

}
