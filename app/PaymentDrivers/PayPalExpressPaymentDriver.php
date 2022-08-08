<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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
use Omnipay\Common\Item;
use Omnipay\Omnipay;

class PayPalExpressPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    private $omnipay_gateway;

    private float $fee = 0;

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public function gatewayTypes()
    {
        return [
            GatewayType::PAYPAL,
        ];
    }

    /**
     * Initialize Omnipay PayPal_Express gateway.
     *
     * @return void
     */
    private function initializeOmnipayGateway(): void
    {
        $this->omnipay_gateway = Omnipay::create(
            $this->company_gateway->gateway->provider
        );

        $this->omnipay_gateway->initialize((array) $this->company_gateway->getConfig());
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

        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $response = $this->omnipay_gateway
            ->purchase($this->generatePaymentDetails($data))
            ->setItems($this->generatePaymentItems($data))
            ->send();

        if ($response->isRedirect()) {
            return $response->redirect();
        }

        $this->sendFailureMail($response->getMessage() ?: '');

        $message = [
            'server_response' => $response->getMessage(),
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

    public function processPaymentResponse($request)
    {
        $this->initializeOmnipayGateway();

        $response = $this->omnipay_gateway
            ->completePurchase(['amount' => $this->payment_hash->data->amount, 'currency' => $this->client->getCurrencyCode()])
            ->send();

        if ($response->isCancelled()) {
            return redirect()->route('client.invoices.index')->with('warning', ctrans('texts.status_cancelled'));
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
            'cancelUrl' => $this->client->company->domain().'/client/invoices',
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
