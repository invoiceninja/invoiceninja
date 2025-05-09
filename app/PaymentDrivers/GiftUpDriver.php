<?php

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\Payment;
use App\Models\SystemLog;
use App\PaymentDrivers\BaseDriver;
use App\Exceptions\PaymentFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GiftUpDriver extends BaseDriver
{
    public $refundable = false;
    public $token_billing = false;
    public $can_authorise_credit_card = false;

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_CUSTOM;

    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;
        return $this;
    }

    public function authorizeView(array $data)
    {
        return render('payment_gateway_views.giftup.authorize', $data);
    }

    public function authorizeResponse(Request $request)
    {
        throw new PaymentFailed("Authorization is not supported.");
    }

    public function processPaymentView(array $data)
    {
        return render('payment_gateway_views.giftup.pay', $data);
    }

    public function processPaymentResponse(Request $request)
    {
        $giftCardCode = $request->input('gift_card_code');
        $amount = $this->payment_hash->amount_with_fee;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->company_gateway->getConfigField('api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.giftup.app/gift-cards/redeem', [
            'code' => $giftCardCode,
            'amount' => $amount,
        ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Gift card redemption failed.';
            throw new PaymentFailed($error);
        }

        $this->confirmGatewayFee();

        $payment = $this->createPayment($amount, [
            'transaction_reference' => $giftCardCode,
        ]);

        return redirect()->route('client.invoices.show', [
            'invoice' => $this->payment_hash->invoice_id,
        ]);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        throw new PaymentFailed('Refunds are not supported via GiftUp.');
    }

    public function tokenBilling(ClientGatewayToken $token, \App\Models\PaymentHash $payment_hash)
    {
        throw new PaymentFailed('Token billing is not supported via GiftUp.');
    }
}
