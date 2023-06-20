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

    public function gatewayTypes()
    {
        return [
            GatewayType::PAYPAL,
        ];
    }

    public function init()
    {
        return $this;
    }

    /**
     * Initialize Omnipay PayPal_Express gateway.
     *
     * @return void
     */
    private function initializeOmnipayGateway(): self
    {
        $this->omnipay_gateway = Omnipay::create(
            $this->company_gateway->gateway->provider
        );

        $this->omnipay_gateway->initialize((array) $this->company_gateway->getConfig());

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
        $this->initializeOmnipayGateway();

        $data['gateway'] = $this;
        
        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $access_token = $this->omnipay_gateway->getToken();

        $headers = [
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Accept-Language' => 'en_US',
        ];

        $r = Http::withToken($access_token)
                ->withHeaders($headers)
                ->post("https://api-m.sandbox.paypal.com/v1/identity/generate-token",['body' => '']);

        nlog($r->body());
        dd($r);

        return render('gateways.paypal.pay', $data);

    }

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
