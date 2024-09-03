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

class Blockonomics implements MethodInterface
{
    use MakesHash;

    public $driver_class;

    public function __construct(BlockonomicsPaymentDriver $driver_class)
    {
        $this->driver_class = $driver_class;
        $this->driver_class->init();
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

    public function paymentView($data)
    {
        $data['gateway'] = $this->driver_class;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->driver_class->client->getCurrencyCode();

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
            strlen($drv->api_key) < 1
        ) {
            throw new PaymentFailed('Blockonomics is not well configured');
        }

        try {
            // This is where the payment happens
            // Will probably have to implement a curl request to the blockonomics API 
            // to create a new payment request
            // Or potentially use an off-site solution

            $_invoice = collect($drv->payment_hash->data->invoices)->first();
            $cli = $drv->client;

            $metaData = [
                'buyerName' => $cli->name,
                'buyerAddress1' => $cli->address1,
                'buyerAddress2' =>  $cli->address2,
                'buyerCity' =>  $cli->city,
                'buyerState' => $cli->state,
                'buyerZip' => $cli->postal_code,
                'buyerCountry' => $cli->country_id,
                'buyerPhone' => $cli->phone,
                'itemDesc' => "From InvoiceNinja",
                'InvoiceNinjaPaymentHash' => $drv->payment_hash->hash
            ];


            $urlRedirect = redirect()->route('client.invoice.show', ['invoice' => $_invoice->invoice_id])->getTargetUrl();

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
