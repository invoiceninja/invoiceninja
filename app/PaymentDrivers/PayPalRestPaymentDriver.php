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

use Omnipay\Omnipay;
use App\Models\Invoice;
use Omnipay\Common\Item;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use Illuminate\Support\Facades\Http;

class PayPalRestPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    private $omnipay_gateway;

    private float $fee = 0;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    private string $api_endpoint_url = '';

    public function gatewayTypes()
    {
        return [
            GatewayType::PAYPAL,
        ];
    }

    public function init()
    {
        $this->omnipay_gateway = Omnipay::create(
            $this->company_gateway->gateway->provider
        );

        $this->omnipay_gateway->initialize((array) $this->company_gateway->getConfig());

        $this->api_endpoint_url = $this->company_gateway->getConfigField('testMode') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        return $this;
    }

    public function setPaymentMethod($payment_method_id)
    {
        // PayPal doesn't have multiple ways of paying.
        // There's just one, off-site redirect.

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

        return render('gateways.paypal.pay', $data);

    }

    public function processPaymentResponse($request)
    {
        $this->init();

        nlog($request->all());
        
        $response = json_decode($request['gateway_response'], true);
        
        $order_id = $response['orderID'];

        nlog($order_id);

        $r = $this->gatewayRequest("/v2/checkout/orders/{$order_id}/capture", 'post', []);

            dd($r->body());

    }

    private function getClientToken(): string
    {

        $r = $this->gatewayRequest('/v1/identity/generate-token', 'post', ['body' => '']);

        if($r->successful()) 
            return $r->json()['client_token'];
        
        throw new PaymentFailed('Unable to gain client token from Paypal. Check your configuration', 401);

    }

    private function createOrder(array $data): string
    {

        $_invoice = collect($this->payment_hash->data->invoices)->first();

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $order = [
          "intent" => "CAPTURE",
          "purchase_units" => [
                [
            "description" =>ctrans('texts.invoice_number').'# '.$invoice->number,
            "invoice_id" => $invoice->number,
            'reference_id' => 'PUHF',
            'description' => 'Sporting Goods',
            'custom_id' => 'CUST-HighFashions',
            'soft_descriptor' => 'HighFashions',
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

        return $r->json()['id'];

    }

    public function gatewayRequest(string $uri, string $verb, array $data, ?array $headers = [])
    {
        $r = Http::withToken($this->omnipay_gateway->getToken())
                ->withHeaders($this->getHeaders($headers))
                ->{$verb}("{$this->api_endpoint_url}{$uri}", $data);

        if($r->successful()) 
            return $r;

        throw new PaymentFailed("Gateway failure - {$r->body()}", 401);

    }

    private function getHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Accept-Language' => 'en_US',
        ], $headers);
    }

    /*
    public function processPaymentResponse($request)
    {
        $this->initializeOmnipayGateway();

        $response = $this->omnipay_gateway
            ->completePurchase(['amount' => $this->payment_hash->data->amount, 'currency' => $this->client->getCurrencyCode()])
            ->send();

        if ($response->isCancelled() && $this->client->getSetting('enable_client_portal')) {
            return redirect()->route('client.invoices.index')->with('warning', ctrans('texts.status_cancelled'));
        } elseif ($response->isCancelled() && !$this->client->getSetting('enable_client_portal')) {
            redirect()->route('client.invoices.show', ['invoice' => $this->payment_hash->fee_invoice])->with('warning', ctrans('texts.status_cancelled'));
        }

        if ($response->isSuccessful()) {
            $data = [
                'payment_method' => $response->getData()['TOKEN'],
                'payment_type' => PaymentType::PAYPAL,
                'amount' => $this->payment_hash->data->amount,
                'transaction_reference' => $response->getTransactionReference(),
                'gateway_type_id' => GatewayType::PAYPAL,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => (array) $response->getData(), 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
        }

        if (! $response->isSuccessful()) {
            $data = $response->getData();

            $this->sendFailureMail($response->getMessage() ?: '');

            $message = [
                'server_response' => $data['L_LONGMESSAGE0'],
                'data' => $this->payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            throw new PaymentFailed($response->getMessage(), $response->getCode());
        }
    }

    public function generatePaymentDetails(array $data)
    {
        $_invoice = collect($this->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        // $this->fee = $this->feeCalc($invoice, $data['total']['amount_with_fee']);

        return [
            'currency' => $this->client->getCurrencyCode(),
            'transactionType' => 'Purchase',
            'clientIp' => request()->getClientIp(),
            // 'amount' => round(($data['total']['amount_with_fee'] + $this->fee),2),
            'amount' => round($data['total']['amount_with_fee'], 2),
            'returnUrl' => route('client.payments.response', [
                'company_gateway_id' => $this->company_gateway->id,
                'payment_hash' => $this->payment_hash->hash,
                'payment_method_id' => GatewayType::PAYPAL,
            ]),
            'cancelUrl' => $this->client->company->domain()."/client/invoices/{$invoice->hashed_id}",
            'description' => implode(',', collect($this->payment_hash->data->invoices)
                ->map(function ($invoice) {
                    return sprintf('%s: %s', ctrans('texts.invoice_number'), $invoice->invoice_number);
                })->toArray()),
            'transactionId' => $this->payment_hash->hash.'-'.time(),
            'ButtonSource' => 'InvoiceNinja_SP',
            'solutionType' => 'Sole',
        ];
    }

    public function generatePaymentItems(array $data)
    {
        $_invoice = collect($this->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $items = [];

        $items[] = new Item([
            'name' => ' ',
            'description' => ctrans('texts.invoice_number').'# '.$invoice->number,
            'price' => $data['total']['amount_with_fee'],
            'quantity' => 1,
        ]);

        return $items;
    }

    */

    private function feeCalc($invoice, $invoice_total)
    {
        $invoice->service()->removeUnpaidGatewayFees();
        $invoice = $invoice->fresh();

        $balance = floatval($invoice->balance);

        $_updated_invoice = $invoice->service()->addGatewayFee($this->company_gateway, GatewayType::PAYPAL, $invoice_total)->save();

        if (floatval($_updated_invoice->balance) > $balance) {
            $fee = floatval($_updated_invoice->balance) - $balance;

            $this->payment_hash->fee_total = $fee;
            $this->payment_hash->save();

            return $fee;
        }

        return 0;
    }

    
}
