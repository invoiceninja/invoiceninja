<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Blockonomics;

use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\BlockonomicsPaymentDriver;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Jobs\Mail\PaymentFailureMailer;
use Illuminate\Support\Facades\Http;

class Blockonomics implements MethodInterface
{
    use MakesHash;

    public function __construct(public BlockonomicsPaymentDriver $blockonomics)
    {
    }

    public function authorizeView($data)
    {
    }

    public function authorizeRequest($request)
    {
    }

    public function authorizeResponse($request)
    {
    }


    public function getBTCAddress(): string
    {
        $api_key = $this->blockonomics->api_key;
        $params = config('ninja.environment') == 'development' ? '?reset=1' : ''; 
        $url = 'https://www.blockonomics.co/api/new_address' . $params;

        $r = Http::withToken($api_key)
                    ->post($url, []);

        if($r->successful())
            return $r->object()->address ?? 'Something went wrong';

        return $r->object()->message ?? 'Something went wrong';

    }

    public function getBTCPrice()
    {
        $currency_code = $this->blockonomics->client->getCurrencyCode();
        $BLOCKONOMICS_BASE_URL = 'https://www.blockonomics.co';
        $BLOCKONOMICS_PRICE_URL = $BLOCKONOMICS_BASE_URL . '/api/price?currency=';
        $response = file_get_contents($BLOCKONOMICS_PRICE_URL . $currency_code);
        $data = json_decode($response, true);
        // TODO: handle error
        return $data['price'];
    }

    public function paymentView($data)
    {
        $btc_price = $this->getBTCPrice();
        $btc_address = $this->getBTCAddress();
        $fiat_amount = $data['total']['amount_with_fee'];
        $btc_amount = $fiat_amount / $btc_price;
        $_invoice = collect($this->blockonomics->payment_hash->data->invoices)->first();
        $data['gateway'] = $this->blockonomics;
        $data['company_gateway_id'] = $this->blockonomics->getCompanyGatewayId();
        $data['amount'] = $fiat_amount;
        $data['currency'] = $this->blockonomics->client->getCurrencyCode();
        $data['btc_amount'] = number_format($btc_amount, 10, '.', '');
        $data['btc_address'] = $btc_address;
        $data['btc_price'] = $btc_price;
        $data['invoice_number'] = $_invoice->invoice_number;
        return render('gateways.blockonomics.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $request->validate([
            'payment_hash' => ['required'],
            'amount' => ['required'],
            'currency' => ['required'],
            'txid' => ['required'],
            'payment_method_id' => ['required'],
        ]);

        try {
            $data = [];
            $fiat_amount = $request->btc_price * $request->btc_amount;
            $data['amount'] = $fiat_amount;
            $data['currency'] = $request->currency;
            $data['payment_method_id'] = $request->payment_method_id;
            $data['payment_type'] = PaymentType::CRYPTO;
            $data['gateway_type_id'] = GatewayType::CRYPTO;
            $data['transaction_reference'] = $request->txid;

            $statusId = Payment::STATUS_PENDING;
            $payment = $this->blockonomics->createPayment($data, $statusId);
            
            SystemLogger::dispatch(
                ['response' => $payment, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_BLOCKONOMICS,
                $this->blockonomics->client,
                $this->blockonomics->client->company,
            );
            
            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

        } catch (\Throwable $e) {
            $blockonomics = $this->blockonomics;
            PaymentFailureMailer::dispatch($blockonomics->client, $blockonomics->payment_hash->data, $blockonomics->client->company, $request->amount);
            throw new PaymentFailed('Error during Blockonomics payment : ' . $e->getMessage());
        }
    }

    // Not supported yet
    public function refund(Payment $payment, $amount)
    {

    }
}
