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

namespace App\PaymentDrivers\LawPay;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\LawPayPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Validator;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public $lawpay;

    public function __construct(LawPayPaymentDriver $lawpay)
    {
        $this->lawpay = $lawpay;
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->lawpay;

        return render('gateways.lawpay.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $token = $request->payment_token;
        $card_brand = $request->card_brand ?? '';
        $last4 = $request->last_4 ?? '';
        $exp_month = $request->exp_month ?? '';
        $exp_year = $request->exp_year ?? '';

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = (string) $exp_month;
        $payment_meta->exp_year = (string) $exp_year;
        $payment_meta->brand = (string) $card_brand;
        $payment_meta->last4 = (string) $last4;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $token,
            'payment_method_id' => GatewayType::CREDIT_CARD,
        ];

        $this->lawpay->storeGatewayToken($data, []);

        return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.lawpay.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();

        // Handle token billing with saved payment method
        if (strlen($request->token ?? '') > 3) {
            $cgt = \App\Models\ClientGatewayToken::query()
                ->where('id', $this->decodePrimaryKey($request->token))
                ->where('client_id', $this->lawpay->client->id)
                ->firstOrFail();

            $payment = $this->lawpay->tokenBilling($cgt, $payment_hash);

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
        }

        // One-time token charge from hosted fields
        $amount_with_fee = $payment_hash->data->amount_with_fee;

        $payload = [
            'amount' => $this->lawpay->convertToGatewayAmount($amount_with_fee),
            'method' => $request->payment_token,
            'reference' => $payment_hash->hash,
        ];

        try {
            $response = $this->lawpay->gatewayRequest('post', 'charges', $payload);
        } catch (\Throwable $e) {
            $this->lawpay->processInternallyFailedPayment($this->lawpay, $e);
        }

        if ($response->successful()) {
            $lawpay_response = $response->json();

            SystemLogger::dispatch(
                ['server_response' => $lawpay_response, 'data' => $payment_hash->data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                LawPayPaymentDriver::SYSTEM_LOG_TYPE,
                $this->lawpay->client,
                $this->lawpay->client->company,
            );

            $data = [
                'payment_method' => $request->payment_method_id,
                'payment_type' => PaymentType::parseCardType(strtolower($request->card_brand ?? '')) ?: PaymentType::CREDIT_CARD_OTHER,
                'amount' => $amount_with_fee,
                'transaction_reference' => $lawpay_response['id'] ?? $lawpay_response['transaction_id'] ?? '',
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $payment = $this->lawpay->createPayment($data, Payment::STATUS_COMPLETED);

            // Store token for future billing if requested
            if ($request->store_card) {
                $payment_meta = new \stdClass();
                $payment_meta->exp_month = (string) ($request->exp_month ?? '');
                $payment_meta->exp_year = (string) ($request->exp_year ?? '');
                $payment_meta->brand = (string) ($request->card_brand ?? '');
                $payment_meta->last4 = (string) ($request->last_4 ?? '');
                $payment_meta->type = GatewayType::CREDIT_CARD;

                $token_data = [
                    'payment_meta' => $payment_meta,
                    'token' => $request->payment_token,
                    'payment_method_id' => GatewayType::CREDIT_CARD,
                ];

                $this->lawpay->storeGatewayToken($token_data, []);
            }

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
        }

        // Handle failure
        $error = $response->json();
        $error_message = $error['message'] ?? $error['error'] ?? 'Payment failed';

        SystemLogger::dispatch(
            ['server_response' => $error, 'data' => $payment_hash->data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            LawPayPaymentDriver::SYSTEM_LOG_TYPE,
            $this->lawpay->client,
            $this->lawpay->client->company,
        );

        $validator = Validator::make([], []);
        $validator->getMessageBag()->add('gateway_error', $error_message);

        return redirect()->route('client.invoice.show', ['invoice' => $payment_hash->fee_invoice->hashed_id])->withErrors($validator);
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.lawpay.credit_card.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $this->lawpay->payment_hash->data = array_merge((array) $this->lawpay->payment_hash->data, $data);
        $this->lawpay->payment_hash->save();

        $data['gateway'] = $this->lawpay;

        return $data;
    }
}
