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
use App\PaymentDrivers\BlockonomicsPaymentDriver;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Exceptions\PaymentFailed;
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
        $this->driver_class = $driver_class;
        $this->driver_class->init();
        // TODO: set invoice_id
        $this->invoice_id = "123";
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
        $api_key = $this->driver_class->api_key;
        $url = 'https://www.blockonomics.co/api/new_address';

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

    public function getBTCPrice()
    {
        $currency_code = $this->driver_class->client->getCurrencyCode();
        $BLOCKONOMICS_BASE_URL = 'https://www.blockonomics.co';
        $BLOCKONOMICS_PRICE_URL = $BLOCKONOMICS_BASE_URL . '/api/price?currency=';
        $response = file_get_contents($BLOCKONOMICS_PRICE_URL . $currency_code);
        $data = json_decode($response, true);
        // TODO: handle error
        return $data['price'];
    }

    public function paymentView($data)
    {
        $data['gateway'] = $this->driver_class;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->driver_class->client->getCurrencyCode();
        $btc_amount = $data['amount'] / $this->getBTCPrice();
        $data['btc_amount'] = round($btc_amount, 10);
        $data['btc_address'] = $this->getBTCAddress();
        $data['invoice_id'] = $this->invoice_id;
        return render('gateways.blockonomics.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {

        $request->validate([
            'payment_hash' => ['required'],
            'amount' => ['required'],
            'currency' => ['required'],
        ]);

        $drv = $this->driver_class;
        if (
            strlen($drv->api_key) < 1 ||
            strlen($drv->callback_secret) < 1 ||
            strlen($drv->callback_url) < 1
        ) {
            throw new PaymentFailed('Blockonomics is not well configured');
        }

        try {
            // $data = [
            //     'payment_method' => '',
            //     'payment_type' => PaymentType::CRYPTO,
            //     'amount' => 200,
            //     'transaction_reference' => 123,
            //     'gateway_type_id' => GatewayType::CRYPTO,
            // ];

            // $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);
            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey(6)]);

        } catch (\Throwable $e) {
            PaymentFailureMailer::dispatch($drv->client, $drv->payment_hash->data, $drv->client->company, $request->amount);
            throw new PaymentFailed('Error during Blockonomics payment : ' . $e->getMessage());
        }
    }

    // Not supported yet
    public function refund(Payment $payment, $amount)
    {

    }
}
