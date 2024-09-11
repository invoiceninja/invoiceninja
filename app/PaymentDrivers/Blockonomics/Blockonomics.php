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
use Illuminate\Mail\Mailables\Address;
use App\Services\Email\EmailObject;
use App\Services\Email\Email;
use Illuminate\Support\Facades\App;
use App\Models\Invoice;

class Blockonomics implements MethodInterface
{
    use MakesHash;

    public $driver_class;

    public function __construct(BlockonomicsPaymentDriver $driver_class)
    {
        $this->blockonomics = $driver_class;
        $this->blockonomics->init();
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


    public function getBTCAddress()
    {
        $api_key = $this->blockonomics->api_key;
        // TODO: remove ?reset=1 before marking PR as ready
        $url = 'https://www.blockonomics.co/api/new_address?reset=1';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        $header = "Authorization: Bearer " . $api_key;
        $headers = array();
        $headers[] = $header;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $contents = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "Error:" . curl_error($ch);
        }

        $responseObj = json_decode($contents);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close ($ch);

        if ($status == 200) {
            return $responseObj->address;
        } else {
            echo "ERROR: " . $status . ' ' . $responseObj->message;
        }
        return "Something went wrong";
    }

    public function getTenMinutesCountDownEndTime()
    {
        $duration_in_sec = 10 * 60; // 10 minutes in seconds
        $current_time = time();
        return $current_time + $duration_in_sec;
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
        $_invoice = collect($this->blockonomics->payment_hash->data->invoices)->first();
        $data['gateway'] = $this->blockonomics;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->blockonomics->client->getCurrencyCode();
        $btc_price = $this->getBTCPrice();
        $btc_amount = $data['amount'] / $btc_price;
        $data['btc_amount'] = number_format($btc_amount, 10, '.', '');
        $btc_address = $this->getBTCAddress();
        $data['btc_address'] = $btc_address;
        $data['btc_price'] = $btc_price;
        $data['invoice_id'] = $_invoice->invoice_id;
        $data['invoice_number'] = $_invoice->invoice_number;
        $data['end_time'] = $this->getTenMinutesCountDownEndTime();
        $data['invoice_redirect_url'] = "/client/invoices/{$_invoice->invoice_id}";

        $data['websocket_url'] = 'wss://www.blockonomics.co/payment/' . $btc_address;
        return render('gateways.blockonomics.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        echo "Payment response received";
        $request->validate([
            'payment_hash' => ['required'],
            'amount' => ['required'],
            'currency' => ['required'],
        ]);

        try {
            $data = [];
            $data['amount'] = $request->amount;
            $data['payment_method_id'] = $request->payment_method_id;
            $data['payment_type'] = PaymentType::CRYPTO;
            $data['gateway_type_id'] = GatewayType::CRYPTO;
            $data['transaction_reference'] = "payment hash: " . $request->payment_hash . " txid: " . $request->txid;
            $data['txid'] = $request->txid;

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
            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

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
