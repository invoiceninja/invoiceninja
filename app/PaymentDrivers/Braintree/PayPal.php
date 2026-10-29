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

namespace App\PaymentDrivers\Braintree;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Utils\Traits\MakesHash;

class PayPal implements LivewireMethodInterface
{
    use MakesHash;
    /**
     * @var BraintreePaymentDriver
     */
    private $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->braintree;

        return render('gateways.braintree.paypal.authorize', $data);
    }

    public function authorizeResponse($data): \Illuminate\Http\RedirectResponse
    {
        return back();
    }

    /**
     * Credit card payment page.
     *
     * @param array $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.braintree.paypal.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $state = [
            'server_response' => json_decode($request->gateway_response ?? ''),
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->braintree->payment_hash->data = array_merge((array) $this->braintree->payment_hash->data, $state);
        $this->braintree->payment_hash->save();

        $customer = $this->braintree->findOrCreateCustomer();

        $payment_source = $this->paymentSource($request, $customer->id);

        $total_taxes = \App\Models\Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->braintree->payment_hash->invoices(), 'invoice_id')))->withTrashed()->sum('total_taxes');
        $invoice = $this->braintree->payment_hash->fee_invoice;
        $po_number = $invoice->po_number ?? $invoice->number ?? '';

        $data = [
            'amount' => $this->braintree->payment_hash->data->amount_with_fee, //@phpstan-ignore-line
            'deviceData' => $state['client-data'],
            'channel' => 'invoiceninja_BT',
            'options' => [
                'submitForSettlement' => true,
                'paypal' => [
                    'description' => 'Meaningful description.',
                ],
            ],
            'taxAmount' => $total_taxes,
            'purchaseOrderNumber' => substr($po_number, 0, 16),
        ];

        $data = array_replace_recursive($data, $payment_source);

        if ($this->braintree->company_gateway->getConfigField('merchantAccountId')) {
            $data['merchantAccountId'] = $this->braintree->company_gateway->getConfigField('merchantAccountId');
        }

        $result = $this->braintree->gateway->transaction()->sale($data);

        if ($result->success) {
            $this->braintree->logSuccessfulGatewayResponse(
                ['response' => $request->server_response, 'data' => $this->braintree->payment_hash->data],
                SystemLog::TYPE_BRAINTREE
            );

            if ($request->boolean('store_card') && ! $request->filled('token')) {
                $payment_method = $this->braintree->gateway->paymentMethod()->find($result->transaction->paypalDetails->token);

                $this->storePaymentMethod($payment_method, $customer->id);
            }

            return $this->processSuccessfulPayment($result);
        }

        return $this->processUnsuccessfulPayment($result);
    }

    private function paymentSource(PaymentResponseRequest $request, string $customer_id): array
    {
        if ($request->filled('token')) {
            $this->braintree->resolveStoredToken($request->token, GatewayType::PAYPAL);

            return ['paymentMethodToken' => $request->token];
        }

        $gateway_response = json_decode($request->input('gateway_response') ?? '', true);
        $nonce = $gateway_response['nonce'] ?? null;

        if (! is_string($nonce) || $nonce === '') {
            throw new PaymentFailed(ctrans('texts.unable_to_verify_payment_method'));
        }

        $data = ['paymentMethodNonce' => $nonce];

        if ($request->boolean('store_card')) {
            $data['customerId'] = $customer_id;
            $data['options']['storeInVaultOnSuccess'] = true;
        }

        return $data;
    }

    /**
     * Process & complete the successful PayPal transaction.
     *
     * @param $response
     * @return \Illuminate\Http\RedirectResponse
     */
    private function processSuccessfulPayment($response): \Illuminate\Http\RedirectResponse
    {
        $state = $this->braintree->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::PAYPAL,
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction->id,
            'gateway_type_id' => GatewayType::PAYPAL,
        ];

        $payment = $this->braintree->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->braintree->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        $this->braintree->sendFailureMail($response->message);

        $message = [
            'server_response' => $response,
            'data' => $this->braintree->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company
        );

        throw new PaymentFailed($response->message, 0);
    }

    private function storePaymentMethod($method, string $customer_reference)
    {
        try {
            $payment_meta = new \stdClass();
            $payment_meta->email = (string) $method->email;
            $payment_meta->type = GatewayType::PAYPAL;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->token,
                'payment_method_id' => $this->braintree->payment_hash->data->payment_method_id,
            ];

            $this->braintree->storeGatewayToken($data, ['gateway_customer_reference' => $customer_reference]);
        } catch (\Exception $e) {
            return $this->braintree->processInternallyFailedPayment($this->braintree, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.braintree.paypal.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->braintree;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate(array_filter([
            'merchantAccountId' => $this->braintree->company_gateway->getConfigField('merchantAccountId'),
        ]));

        return $data;
    }
}
