<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Blockonomics;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use Illuminate\Support\Facades\Http;
use App\Jobs\Mail\PaymentFailureMailer;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\BlockonomicsPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class Blockonomics implements LivewireMethodInterface
{
    use MakesHash;
    private string $test_txid = 'WarningThisIsAGeneratedTestPaymentAndNotARealBitcoinTransaction';

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


    public function getBTCAddress(): array
    {
        $api_key = $this->blockonomics->company_gateway->getConfigField('apiKey');

        if (!$api_key) {
            return ['success' => false, 'message' => 'Please enter a valid API key'];
        }

        // $params = config('ninja.environment') == 'development' ? '?reset=1' : '';
        $url = 'https://www.blockonomics.co/api/new_address';

        $response = Http::withToken($api_key)
                        ->post($url, []);

        nlog($response->body());

        if ($response->status() == 401) {
            return ['success' => false, 'message' => 'API Key is incorrect'];
        };

        if ($response->successful()) {
            if (isset($response->object()->address)) {
                return ['success' => true, 'address' => $response->object()->address];
            } else {
                return ['success' => false, 'message' => 'Address not returned'];
            }
        } else {
            return ['success' => false, 'message' => "Could not generate new address (This may be a temporary error. Please try again). \n\n<br><br> If this continues, please ask website administrator to check blockonomics registered email address for error messages"];
        }

    }

    public function getBTCPrice()
    {

        $r = Http::get('https://www.blockonomics.co/api/price', ['currency' => $this->blockonomics->client->getCurrencyCode()]);

        return $r->successful() ? $r->object()->price : 'Something went wrong';

    }

    public function paymentData(array $data): array
    {

        $btc_price = $this->getBTCPrice();
        $btc_address = $this->getBTCAddress();
        $data['error'] = null;
        if (!$btc_address['success']) {
            $data['error'] = $btc_address['message'];
        }
        $fiat_amount = $data['total']['amount_with_fee'];
        $btc_amount = $fiat_amount / $btc_price;
        $_invoice = collect($this->blockonomics->payment_hash->data->invoices)->first();
        $data['gateway'] = $this->blockonomics;
        $data['company_gateway_id'] = $this->blockonomics->getCompanyGatewayId();
        $data['amount'] = $fiat_amount;
        $data['currency'] = $this->blockonomics->client->getCurrencyCode();
        $data['btc_amount'] = number_format($btc_amount, 10, '.', '');
        $data['btc_address'] = $btc_address['address'] ?? '';
        $data['btc_price'] = $btc_price;
        $data['invoice_number'] = $_invoice->invoice_number;

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.blockonomics.pay_livewire';
    }

    public function paymentView($data)
    {
        $data = $this->paymentData($data);

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
            'btc_address' => ['required'],
            'btc_amount' => ['required'],
            'btc_price' => ['required'],
            // Setting status to required will break the payment process
            // because sometimes the status is returned as 0 which is falsy
            // and the validation will fail.
            // 'status' => ['required'],
        ]);

        try {
            $data = [];
            $fiat_amount = round(($request->btc_price * $request->btc_amount / 100000000), 2);
            $data['amount'] = $fiat_amount;
            $data['payment_method_id'] = $request->payment_method_id;
            $data['payment_type'] = PaymentType::CRYPTO;
            $data['gateway_type_id'] = GatewayType::CRYPTO;

            // Append a random value to the transaction reference for test payments
            // to prevent duplicate entries in the database.
            // This ensures the payment hashed_id remains unique.
            $testTxid = $this->test_txid;
            $data['transaction_reference'] = ($request->txid === $testTxid)
                ? $request->txid . bin2hex(random_bytes(16))
                : $request->txid;

            $statusId;

            switch ($request->status) {
                case 0:
                    $statusId = Payment::STATUS_PENDING;
                    break;
                case 1:
                    $statusId = Payment::STATUS_PENDING;
                    break;
                case 2:
                    $statusId = Payment::STATUS_COMPLETED;
                    break;
                default:
                    $statusId = Payment::STATUS_PENDING;
            }

            $payment = $this->blockonomics->createPayment($data, $statusId);
            $payment->private_notes = "{$request->btc_address} - {$request->btc_amount}";
            $payment->save();

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
        return;
    }
}
