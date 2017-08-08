<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class CreatePaymentIntent extends BaseIntent
{
    public function process()
    {
        $clientPublicId = 0;
        $invoicePublicId = 0;

        if ($invoice = $this->requestInvoice()) {
            $invoicePublicId = $invoice->public_id;
        } elseif ($client = $this->requestClient()) {
            $clientPublicId = $client->public_id;
        }

        //$invoiceItems = $this->requestInvoiceItems();

        $url = sprintf('/payments/create/%s/%s', $clientPublicId, $invoicePublicId);
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
