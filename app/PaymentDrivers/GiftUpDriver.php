<?php

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\GatewayType;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GiftUpDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    /**
     * Return the display name.
     */
    public function gatewayName(): string
    {
        return 'Gift Up!';
    }

    /**
     * Payment type identifier.
     */
    public function gatewayTypes(): array
    {
        return [GatewayType::CUSTOM];
    }

    /**
     * Shows form for entering gift card code.
     */
    public function authorizeView(array $data)
    {
        return render('gateways.giftup.authorize', $data);
    }

    /**
     * Called when user submits gift card code form.
     */
    public function authorizeResponse(Request $request)
    {
        $giftCardCode = $request->input('gift_card_code');
        $amount = $this->payment_hash->invoice()->amount; // Amount in base currency

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->getConfigField('api_key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.giftup.app/gift-cards/redeem', [
            'code'   => $giftCardCode,
            'amount' => [
                'value'    => $amount,
                'currency' => $this->company_gateway->company->currency()->code,
            ],
        ]);

        if ($response->successful()) {
            return $this->processSuccessfulPayment($giftCardCode, $amount);
        } else {
            return back()->withErrors(['gift_card_code' => 'Gift card redemption failed: ' . $response->body()]);
        }
    }

    /**
     * Process the successful redemption.
     */
    private function processSuccessfulPayment(string $giftCardCode, float $amount)
    {
        $payment = $this->createPayment($amount, [
            'transaction_reference' => 'GiftUp-' . $giftCardCode,
            'gateway_type_id' => GatewayType::CUSTOM,
        ]);

        return redirect()->route('client.invoices.show', [$this->invitation->invoice_id])
                         ->with('message', 'Gift card applied successfully!');
    }

    /**
     * Not used here — direct payment via gift card only.
     */
    public function processPaymentView(array $data)
    {
        return $this->authorizeView($data);
    }

    /**
     * Not used here — direct payment via gift card only.
     */
    public function processPaymentResponse(Request $request)
    {
        return $this->authorizeResponse($request);
    }

    /**
     * Not used — gift cards are not refunded through the system.
     */
    public function refund(Payment $payment, $refund_amount, $return_client_response = false)
    {
        throw new \Exception('Refunds not supported for Gift Up payments.');
    }

    /**
     * Not used for gift cards.
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        throw new \Exception('Token billing not supported for Gift Up.');
    }

    /**
     * Not needed unless supporting multiple payment methods.
     */
    public function setPaymentMethod($payment_method_id)
    {
        return $this;
    }
}
