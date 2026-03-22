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

namespace App\PaymentDrivers\Helcim;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\HelcimPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public function __construct(public HelcimPaymentDriver $helcim)
    {
    }

    public function authorizeView($data): View
    {
        $data = $this->paymentData($data);
        return render('gateways.helcim.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $gateway_response = json_decode($request->input('gateway_response'), true);

        if (!isset($gateway_response['cardToken'])) {
            throw new PaymentFailed('No card token received from Helcim', 400);
        }

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = (string) ($gateway_response['cardExpiry'] ? substr($gateway_response['cardExpiry'], 0, 2) : '');
        $payment_meta->exp_year = (string) ($gateway_response['cardExpiry'] ? substr($gateway_response['cardExpiry'], 2, 2) : '');
        $payment_meta->brand = (string) ($gateway_response['cardType'] ?? 'CARD');
        $payment_meta->last4 = (string) ($gateway_response['cardNumber'] ?? '');
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $gateway_response['cardToken'],
            'payment_method_id' => GatewayType::CREDIT_CARD,
        ];

        $this->helcim->storeGatewayToken($data, []);

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView($data)
    {
        $data = $this->paymentData($data);
        return render('gateways.helcim.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $gateway_response = json_decode($request->input('gateway_response'), true);

        if (!$gateway_response) {
            throw new PaymentFailed('Invalid gateway response', 400);
        }

        // Check if using saved token
        if ($request->has('token') && $request->input('token')) {
            $cgt = \App\Models\ClientGatewayToken::find($this->decodePrimaryKey($request->input('token')));
            
            if ($cgt) {
                return $this->processTokenPayment($cgt, $request);
            }
        }

        // Process new card payment
        if (!isset($gateway_response['transactionId'])) {
            throw new PaymentFailed('Transaction failed - no transaction ID', 400);
        }

        $amount = array_sum(array_column($this->helcim->payment_hash->invoices(), 'amount')) + $this->helcim->payment_hash->fee_total;

        // Save card if requested
        if ($request->input('store_card') === 'true' && isset($gateway_response['cardToken'])) {
            $this->storePaymentMethod($gateway_response);
        }

        $payment_record = [
            'amount' => $amount,
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'transaction_reference' => $gateway_response['transactionId'],
        ];

        $payment = $this->helcim->createPayment($payment_record, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $gateway_response, 'data' => $payment_record],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_HELCIM,
            $this->helcim->client,
            $this->helcim->client->company
        );

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function processTokenPayment($cgt, $request)
    {
        return $this->helcim->tokenBilling($cgt, $this->helcim->payment_hash);
    }

    private function storePaymentMethod($gateway_response)
    {
        $payment_meta = new \stdClass();
        $payment_meta->exp_month = (string) ($gateway_response['cardExpiry'] ? substr($gateway_response['cardExpiry'], 0, 2) : '');
        $payment_meta->exp_year = (string) ($gateway_response['cardExpiry'] ? substr($gateway_response['cardExpiry'], 2, 2) : '');
        $payment_meta->brand = (string) ($gateway_response['cardType'] ?? 'CARD');
        $payment_meta->last4 = (string) ($gateway_response['cardNumber'] ?? '');
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $gateway_response['cardToken'],
            'payment_method_id' => GatewayType::CREDIT_CARD,
        ];

        return $this->helcim->storeGatewayToken($data, []);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.helcim.credit_card.pay_livewire';
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->helcim;
        $data['amount'] = $this->helcim->payment_hash->data->amount_with_fee ?? 0;
        $data['currency'] = $this->helcim->client->currency()->code;

        return $data;
    }
}