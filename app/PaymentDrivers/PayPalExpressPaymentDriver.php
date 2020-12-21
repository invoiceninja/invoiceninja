<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
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
        if (count($this->required_fields) > 0) {
            return redirect()
                ->route('client.profile.edit', ['client_contact' => auth()->user()->hashed_id])
                ->with('missing_required_fields', $this->required_fields);
        }

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

        PaymentFailureMailer::dispatch($this->client, $response->getData(), $this->client->company, $data['total']['amount_with_fee']);

        $message = [
            'server_response' => $response->getMessage(),
            'data' => $this->checkout->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYPAL,
            $this->client
        );

        throw new PaymentFailed($response->getMessage(), $response->getCode());
    }

    public function processPaymentResponse($request)
    {
        if (count($this->required_fields) > 0) {
            return redirect()
                ->route('client.profile.edit', ['client_contact' => auth()->user()->hashed_id])
                ->with('missing_required_fields', $this->required_fields);
        }

        $this->initializeOmnipayGateway();

        $response = $this->omnipay_gateway
            ->completePurchase(['amount' => $this->payment_hash->data->amount])
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
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
        }

        if (!$response->isSuccessful()) {
            PaymentFailureMailer::dispatch($this->client, $response->getMessage(), $this->client->company, $response['PAYMENTINFO_0_AMT']);

            $message = [
                'server_response' => $response->getMessage(),
                'data' => $this->payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );

            throw new PaymentFailed($response->getMessage(), $response->getCode());
        }
    }

    public function generatePaymentDetails(array $data)
    {
        return [
            'currency' => $this->client->getCurrencyCode(),
            'transactionType' => 'Purchase',
            'clientIp' => request()->getClientIp(),
            'amount' => $data['total']['amount_with_fee'],
            'returnUrl' => route('client.payments.response', [
                'company_gateway_id' => $this->company_gateway->id,
                'payment_hash' => $this->payment_hash->hash,
                'payment_method_id' => GatewayType::PAYPAL,
            ]),
            'cancelUrl' => $this->client->company->domain() . '/client/invoices',
            'description' => implode(',', collect($this->payment_hash->data->invoices)
                ->map(function ($invoice) {
                    return sprintf('%s: %s', ctrans('texts.invoice_number'), $invoice->invoice_number);
                })->toArray()),
            'transactionId' => $this->payment_hash->hash . '-' . time(),
            'ButtonSource' => 'InvoiceNinja_SP',
            'solutionType' => 'Sole',
        ];
    }

    public function generatePaymentItems(array $data)
    {
        $total = 0;

        $items = collect($this->payment_hash->data->invoices)->map(function ($i) use (&$total) {
            $invoice = Invoice::findOrFail($this->decodePrimaryKey($i->invoice_id));

            return collect($invoice->line_items)->map(function ($lineItem) use (&$total) {
                if (floatval($lineItem->quantity) != intval($lineItem->quantity)) {
                    return null;
                }

                $total += $lineItem->cost * $lineItem->quantity;

                return new Item([
                    'name' => $lineItem->product_key,
                    'description' => substr($lineItem->notes, 0, 100),
                    'price' => $lineItem->cost,
                    'quantity' => $lineItem->quantity,
                ]);
            });
        });

        if ($total != $data['total']['amount_with_fee']) {
            $items[0][] = new Item([
                'name' => trans('texts.taxes_and_fees'),
                'description' => '',
                'price' => $data['total']['amount_with_fee'] - $total,
                'quantity' => 1,
            ]);
        }

        return $items[0]->toArray();
    }
}
