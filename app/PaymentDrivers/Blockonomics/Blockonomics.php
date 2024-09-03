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

            $rep = $client->createInvoice(
                $drv->store_id,
                $request->currency,
                $_invoice->invoice_number,
                $cli->present()->email(),
                $metaData,
                $checkoutOptions
            );

            return redirect($rep->getCheckoutLink());
        } catch (\Throwable $e) {
            PaymentFailureMailer::dispatch($drv->client, $drv->payment_hash->data, $drv->client->company, $request->amount);
            throw new PaymentFailed('Error during Blockonomics payment : ' . $e->getMessage());
        }
    }

    public function refund(Payment $payment, $amount)
    {
        try {
            if ($amount == $payment->amount) {
                $refundVariant = "Fiat";
                $refundPaymentMethod = "BTC";
                $refundDescription = "Full refund";
                $refundCustomCurrency = null;
                $refundCustomAmount = null;
            } else {
                $refundVariant = "Custom";
                $refundPaymentMethod = "";
                $refundDescription = "Partial refund";
                $refundCustomCurrency = $payment->currency;
                $refundCustomAmount = $amount;
            }
            App::setLocale($payment->company->getLocale());

            $email_object = new EmailObject();
            $email_object->subject = ctrans('texts.blockonomics_refund_subject');
            $email_object->body = ctrans('texts.blockonomics_refund_body') . '<br>' . $refund->getViewLink();
            $email_object->text_body = ctrans('texts.blockonomics_refund_body') . '\n' . $refund->getViewLink();
            $email_object->company_key = $payment->company->company_key;
            $email_object->html_template = 'email.template.generic';
            $email_object->to = [new Address($payment->client->present()->email(), $payment->client->present()->name())];
            $email_object->email_template_body = 'blockonomics_refund_subject';
            $email_object->email_template_subject = 'blockonomics_refund_body';

            Email::dispatch($email_object, $payment->company);

            $data = [
                'transaction_reference' => $refund->getId(),
                'transaction_response' => json_encode($refund),
                'success' => true,
                'description' => "Please follow this link to claim your refund: " . $refund->getViewLink(),
                'code' => 202,
            ];

            return $data;
        } catch (\Throwable $e) {
            throw new PaymentFailed('Error during Blockonomics refund : ' . $e->getMessage());
        }
    }
}
