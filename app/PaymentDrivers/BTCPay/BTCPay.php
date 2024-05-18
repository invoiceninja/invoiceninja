<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\BTCPay;

use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\PaymentDrivers\BTCPayPaymentDriver;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Exceptions\PaymentFailed;

class BTCPay implements MethodInterface
{
    use MakesHash;

    public $driver_class;

    public function __construct(BTCPayPaymentDriver $driver_class)
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

        return render('gateways.btcpay.pay', $data);
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
            strlen($drv->btcpay_url) < 1
            || strlen($drv->api_key) < 1
            || strlen($drv->store_id) < 1
            || strlen($drv->webhook_secret) < 1
        ) {
            throw new PaymentFailed('BTCPay is not well configured');
        }
        if (!filter_var($this->driver_class->btcpay_url, FILTER_VALIDATE_URL)) {
            throw new PaymentFailed('Wrong format for BTCPay Url');
        }

        try {
            $_invoice = collect($drv->payment_hash->data->invoices)->first();
            $cli = $drv->client;

            $dataPayment = [
                'payment_method' => $drv->payment_method,
                'payment_type' => PaymentType::CRYPTO,
                'amount' => $request->amount,
                'gateway_type_id' => GatewayType::CRYPTO,
                'transaction_reference' =>  'xxx'
            ];
            $payment = $drv->createPayment($dataPayment, \App\Models\Payment::STATUS_PENDING);

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
                'paymentID' => $payment->id
            ];


            $urlRedirect = redirect()->route('client.payments.show', ['payment' => $payment->hashed_id])->getTargetUrl();
            $checkoutOptions = new \BTCPayServer\Client\InvoiceCheckoutOptions();
            $checkoutOptions->setRedirectURL($urlRedirect);

            $client = new \BTCPayServer\Client\Invoice($drv->btcpay_url, $drv->api_key);
            $rep = $client->createInvoice(
                $drv->store_id,
                $request->currency,
                \BTCPayServer\Util\PreciseNumber::parseString($request->amount),
                $_invoice->invoice_number,
                $cli->present()->email(),
                $metaData,
                $checkoutOptions
            );
            $payment->transaction_reference = $rep->getId();
            $payment->save();

            return redirect($rep->getCheckoutLink());
        } catch (\Throwable $e) {
            throw new PaymentFailed('Error during BTCPay payment : ' . $e->getMessage());
        }
    }

    public function refund(Payment $payment, $amount)
    {
        try {
            $invoice = $payment->invoices()->first();
            $isPartialRefund = ($amount < $payment->amount);

            $client = new \BTCPayServer\Client\Invoice($this->driver_class->btcpay_url, $this->driver_class->api_key);
            $refund = $client->refundInvoice($this->driver_class->store_id,  $payment->transaction_reference);

           /* $data = [];
            $data['InvoiceNumber'] = $invoice->number;
            $data['isPartialRefund'] = $isPartialRefund;
            $data['BTCPayLink'] = $refund->getViewLink();*/

            return $refund->getViewLink();
        } catch (\Throwable $e) {
            throw new PaymentFailed('Error during BTCPay refund : ' . $e->getMessage());
        }
    }
}
