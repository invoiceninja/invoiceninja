<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use Str;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use Illuminate\Support\Facades\Http;
use App\PaymentDrivers\PayPal\PayPalWebhook;

class PayPalPPCPPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    private $omnipay_gateway;

    private float $fee = 0;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL_PPCP;

    private string $api_endpoint_url = '';

    private string $paypal_payment_method = '';

    private ?int $gateway_type_id = null;

    protected mixed $access_token = null;

    protected ?Carbon $token_expiry = null;

    private array $funding_options = [
        3 => 'paypal',
        1 => 'card',
        25 => 'venmo',
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

    /**
     * Return an array of
     * enabled gateway payment methods
     *
     * @return array
     */
    public function gatewayTypes(): array
    {

        return collect($this->company_gateway->fees_and_limits)
                ->filter(function ($fee) {
                    return $fee->is_enabled;
                })->map(function ($fee, $key) {
                    return (int)$key;
                })->toArray();

    }

    private function getPaymentMethod($gateway_type_id): int
    {
        $method = PaymentType::PAYPAL;

        match($gateway_type_id) {
            "1" => $method = PaymentType::CREDIT_CARD_OTHER,
            "3" => $method = PaymentType::PAYPAL,
            "25" => $method = PaymentType::VENMO,
            "28" => $method = PaymentType::PAY_LATER,
        };

        return $method;
    }

    private function getFundingOptions(): string
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

        foreach($this->company_gateway->fees_and_limits as $key => $value) {

            if($value->is_enabled) {

                $funding_options .= $enums[$key].',';

            }

        }

        return rtrim($funding_options, ',');

    }

    /**
     * Initialize the Paypal gateway.
     *
     * Attempt to generate and return the access token.
     *
     * @return self
     */
    public function init(): self
    {

        $this->api_endpoint_url = 'https://api-m.paypal.com';
        // $this->api_endpoint_url = 'https://api-m.sandbox.paypal.com';
        $secret = config('ninja.paypal.secret');
        $client_id = config('ninja.paypal.client_id');

        if($this->access_token && $this->token_expiry && $this->token_expiry->isFuture()) {
            return $this;
        }

        $response = Http::withBasicAuth($client_id, $secret)
                                    ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                                    ->withQueryParameters(['grant_type' => 'client_credentials'])
                                    ->post("{$this->api_endpoint_url}/v1/oauth2/token");

        if($response->successful()) {
            $this->access_token = $response->json()['access_token'];
            $this->token_expiry = now()->addSeconds($response->json()['expires_in'] - 60);
        } else {
            throw new PaymentFailed('Unable to gain access token from Paypal. Check your configuration', 401);
        }

        return $this;

    }

    /**
     * Payment method setter
     *
     * @param  mixed $payment_method_id
     * @return self
     */
    public function setPaymentMethod($payment_method_id): self
    {
        if(!$payment_method_id) {
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
     * Checks whether payments are enabled on the account
     *
     * @return self
     */
    private function checkPaymentsReceivable(): self
    {

        if($this->company_gateway->getConfigField('status') != 'activated') {

            if (class_exists(\Modules\Admin\Services\PayPal\PayPalService::class)) {
                $pp = new \Modules\Admin\Services\PayPal\PayPalService($this->company_gateway->company, $this->company_gateway->user);
                $pp->updateMerchantStatus($this->company_gateway);

                $this->company_gateway = $this->company_gateway->fresh();
                $config = $this->company_gateway->getConfig();

                if($config->status == 'activated') {
                    return $this;
                }

            }

            throw new PaymentFailed('Unable to accept payments at this time, please contact PayPal for more information.', 401);
        }

        return $this;

    }

    /**
     * Presents the Payment View to the client
     *
     * @param  mixed $data
     * @return void
     */
    public function processPaymentView($data)
    {
        $this->init()->checkPaymentsReceivable();

        $data['gateway'] = $this;
        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $data['client_id'] = config('ninja.paypal.client_id');
        $data['token'] = $this->getClientToken();
        $data['order_id'] = $this->createOrder($data);
        $data['funding_source'] = $this->paypal_payment_method;
        $data['gateway_type_id'] = $this->gateway_type_id;
        $data['merchantId'] = $this->company_gateway->getConfigField('merchantId');
        $data['currency'] = $this->client->currency()->code;
        // nlog($data['merchantId']);

        return render('gateways.paypal.ppcp.pay', $data);

    }

    /**
     * Processes the payment response
     *
     * @param  mixed $request
     * @return void
     */
    public function processPaymentResponse($request)
    {

        $request['gateway_response'] = str_replace("Error: ", "", $request['gateway_response']);
        $response = json_decode($request['gateway_response'], true);

        //capture
        $orderID = $response['orderID'];

        if($this->company_gateway->require_shipping_address) {

            $shipping_data =
            [[
                "op" => "replace",
                "path" => "/purchase_units/@reference_id=='default'/shipping/address",
                "value" => [
                    "address_line_1" => strlen($this->client->shipping_address1) > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => strlen($this->client->shipping_city) > 1 ? $this->client->shipping_city : $this->client->city,
                    "admin_area_1" => strlen($this->client->shipping_state) > 1 ? $this->client->shipping_state : $this->client->state,
                    "postal_code" => strlen($this->client->shipping_postal_code) > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
                ],
            ]];

            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $shipping_data);

        }

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

        $response = $r;

        if(isset($response['status']) && $response['status'] == 'COMPLETED' && isset($response['purchase_units'])) {

            $data = [
                'payment_type' => $this->getPaymentMethod($request->gateway_type_id),
                'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => GatewayType::PAYPAL,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

        } else {

            if(isset($response['headers']) ?? false) {
                unset($response['headers']);
            }

            SystemLogger::dispatch(
                ['response' => $response],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            $message = $response['body']['details'][0]['description'] ?? 'Payment failed. Please try again.';

            throw new PaymentFailed($message, 400);
        }
    }

    public function getOrder(string $order_id)
    {
        $this->init();

        $r = $this->gatewayRequest("/v2/checkout/orders/{$order_id}", 'get', ['body' => '']);

        return $r->json();
    }

    /**
     * Generates a client token for the payment form.
     *
     * @return string
     */
    private function getClientToken(): string
    {

        $r = $this->gatewayRequest('/v1/identity/generate-token', 'post', ['body' => '']);

        if($r->successful()) {
            return $r->json()['client_token'];
        }

        throw new PaymentFailed('Unable to gain client token from Paypal. Check your configuration', 401);

    }

    /**
     * Builds the payment request.
     *
     * @return array
     */
    private function paymentSource(): array
    {
        /** we only need to support paypal as payment source until as we are only using hosted payment buttons */
        return $this->injectPayPalPaymentSource();

    }

    private function injectPayPalPaymentSource(): array
    {

        return [
            "paypal" => [

                "name" => [
                    "given_name" => $this->client->present()->first_name(),
                    "surname" => $this->client->present()->last_name(),
                ],
                "email_address" => $this->client->present()->email(),
                "address" => $this->getBillingAddress(),
                "experience_context" => [
                    "user_action" => "PAY_NOW"
                ],
            ],
        ];

    }

    /**
     * Creates the PayPal Order object
     *
     * @param  array $data
     * @return string
     */
    private function createOrder(array $data): string
    {

        $_invoice = collect($this->payment_hash->data->invoices)->first();

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $description = collect($invoice->line_items)->map(function ($item) {
            return $item->notes;
        })->implode("\n");

        $order = [

                "intent" => "CAPTURE",
                "payment_source" => $this->paymentSource(),
                "purchase_units" => [
                    [
                    "custom_id" => $this->payment_hash->hash,
                    "description" => ctrans('texts.invoice_number').'# '.$invoice->number,
                    "invoice_id" => $invoice->number,
                    "payee" => [
                        "merchant_id" => $this->company_gateway->getConfigField('merchantId'),
                    ],
                    "payment_instruction" => [
                        "disbursement_mode" => "INSTANT",
                    ],
                    $this->getShippingAddress(),
                    "amount" => [
                        "value" => (string)$data['amount_with_fee'],
                        "currency_code" => $this->client->currency()->code,
                        "breakdown" => [
                            "item_total" => [
                                "currency_code" => $this->client->currency()->code,
                                "value" => (string)$data['amount_with_fee']
                            ]
                        ]
                    ],
                    "items" => [
                        [
                            "name" => ctrans('texts.invoice_number').'# '.$invoice->number,
                            "description" => mb_substr($description, 0, 127),
                            "quantity" => "1",
                            "unit_amount" => [
                                "currency_code" => $this->client->currency()->code,
                                "value" => (string)$data['amount_with_fee']
                            ],
                        ],
                    ],
                ],
                ]
            ];


        if($shipping = $this->getShippingAddress()) {
            $order['purchase_units'][0]["shipping"] = $shipping;
        }

        $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);

        // nlog($r->json());

        return $r->json()['id'];

    }

    private function getBillingAddress(): array
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

    private function getShippingAddress(): ?array
    {
        return $this->company_gateway->require_shipping_address ?
        [
            "address" =>
                [
                    "address_line_1" => strlen($this->client->shipping_address1) > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => strlen($this->client->shipping_city) > 1 ? $this->client->shipping_city : $this->client->city,
                    "admin_area_1" => strlen($this->client->shipping_state) > 1 ? $this->client->shipping_state : $this->client->state,
                    "postal_code" => strlen($this->client->shipping_postal_code) > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
                ],
        ]

        : null;

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

        if($r->successful()) {
            return $r;
        }

        SystemLogger::dispatch(
            ['response' => $r->body()],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYPAL,
            $this->client,
            $this->client->company,
        );

        throw new PaymentFailed("Gateway failure - {$r->body()}", 401);

    }

    /**
     * Generates the request headers
     *
     * @param  array $headers
     * @return array
     */
    private function getHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Accept-Language' => 'en_US',
            'PayPal-Partner-Attribution-Id' => 'invoiceninja_SP_PPCP',
            'PayPal-Request-Id' => Str::uuid()->toString(),
        ], $headers);
    }

    public function processWebhookRequest(Request $request)
    {

        // nlog(json_encode($request->all()));
        $this->init();

        PayPalWebhook::dispatch($request->all(), $request->headers->all(), $this->access_token);
    }

}
