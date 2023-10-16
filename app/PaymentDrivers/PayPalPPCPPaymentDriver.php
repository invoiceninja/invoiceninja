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

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Omnipay\Omnipay;

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
        // 28 => 'paylater',
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

        // $funding_options = [];
        
        // foreach ($this->company_gateway->fees_and_limits as $key => $value) {
        //     if ($value->is_enabled) {
        //         $funding_options[] = (int)$key;
        //     }
        // }
        return collect($this->company_gateway->fees_and_limits)
                ->filter(function ($fee){
                    return $fee->is_enabled;
                })->map(function ($fee, $key){
                    return (int)$key;
                })->toArray();
        
        // return $funding_options;

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

        $this->api_endpoint_url = $this->company_gateway->getConfigField('testMode') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        $secret = $this->company_gateway->getConfigField('secret');
        
        $client_id = $this->company_gateway->getConfigField('clientId');

        if($this->access_token && $this->token_expiry && $this->token_expiry->isFuture())
            return $this;

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

    public function setPaymentMethod($payment_method_id)
    {
        if(!$payment_method_id) {
            return $this;
        }

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

    public function processPaymentView($data)
    {
        $this->init();

        $data['gateway'] = $this;
        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $data['client_id'] = $this->company_gateway->getConfigField('clientId');
        $data['token'] = $this->getClientToken();
        $data['order_id'] = $this->createOrder($data);
        $data['funding_source'] = $this->paypal_payment_method;

        return render('gateways.paypal.ppcp.pay', $data);

    }

    private function getClientToken(): string
    {

        $r = $this->gatewayRequest('/v1/identity/generate-token', 'post', ['body' => '']);

        if($r->successful()) 
            return $r->json()['client_token'];
        
        throw new PaymentFailed('Unable to gain client token from Paypal. Check your configuration', 401);

    }

    private function getFundingOptions():string
    {

        $enums = [
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
            // 28 => 'paylater',
            // 16 => 'p24',
            // 7 => 'sofort'
        ];

        $funding_options = '';

        foreach($this->company_gateway->fees_and_limits as $key => $value) {

            if($value->is_enabled) {

                $funding_options .=$enums[$key].',';

            }

        }

        return rtrim($funding_options, ',');

    }

    public function processPaymentResponse($request)
    {

        $response = json_decode($request['gateway_response'], true);
        
        nlog($response);

        if($response['status'] == 'COMPLETED' && isset($response['purchase_units'])) {

            $data = [
                'payment_type' => PaymentType::PAYPAL,
                'amount' => $response['purchase_units'][0]['amount']['value'],
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

            SystemLogger::dispatch(
                ['response' => $response],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );


            throw new PaymentFailed('Payment failed. Please try again.', 401);
        }
    }

    private function createOrder(array $data): string
    {

        $_invoice = collect($this->payment_hash->data->invoices)->first();

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $order = [
          "intent" => "CAPTURE",
          "payer" => [
            "name" => [
                "given_name" => $this->client->present()->first_name(),
                "surname" => $this->client->present()->last_name(),
            ],
            "email_address" => $this->client->present()->email(),
            "address" => [
                "address_line_1" => $this->client->address1,
                "address_line_2" => $this->client->address2,
                "admin_area_1" => $this->client->city,
                "admin_area_2" => $this->client->state,
                "postal_code" => $this->client->postal_code,
                "country_code" => $this->client->country->iso_3166_2,
            ]
            ],
          "purchase_units" => [
                [
            "description" =>ctrans('texts.invoice_number').'# '.$invoice->number,
            "invoice_id" => $invoice->number,
            "amount" => [
                "value" => (string)$data['amount_with_fee'],
                "currency_code"=> $this->client->currency()->code,
                "breakdown" => [
                    "item_total" => [
                        "currency_code" => $this->client->currency()->code,
                        "value" => (string)$data['amount_with_fee']
                    ]
                ]
            ],
            "items"=> [
                [
                    "name" => ctrans('texts.invoice_number').'# '.$invoice->number,
                    "quantity" => "1",
                    "unit_amount" => [
                        "currency_code" => $this->client->currency()->code,
                        "value" => (string)$data['amount_with_fee']
                    ],
                ],
            ],
          ]
          ]
        ];
        
        $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);

        nlog($r->json());

        return $r->json()['id'];

    }

    public function gatewayRequest(string $uri, string $verb, array $data, ?array $headers = [])
    {
        $this->init();
        
        $r = Http::withToken($this->access_token)
                ->withHeaders($this->getHeaders($headers))
                ->{$verb}("{$this->api_endpoint_url}{$uri}", $data);

        if($r->successful()) {
            return $r;
        }

        throw new PaymentFailed("Gateway failure - {$r->body()}", 401);

    }

    private function getHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Accept-Language' => 'en_US',
            'PayPal-Partner-Attribution-Id' => 'invoiceninja_SP_PPCP'
        ], $headers);
    }

    private function feeCalc($invoice, $invoice_total)
    {
        //     $invoice->service()->removeUnpaidGatewayFees();
        //     $invoice = $invoice->fresh();

        //     $balance = floatval($invoice->balance);

        //     $_updated_invoice = $invoice->service()->addGatewayFee($this->company_gateway, GatewayType::PAYPAL, $invoice_total)->save();

        //     if (floatval($_updated_invoice->balance) > $balance) {
        //         $fee = floatval($_updated_invoice->balance) - $balance;

        //         $this->payment_hash->fee_total = $fee;
        //         $this->payment_hash->save();

        //         return $fee;
        //     }

        //     return 0;
        // }


    }
}