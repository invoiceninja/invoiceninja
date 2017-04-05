<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class CreateCreditIntent extends BaseIntent
{
    public function process()
    {
        $client = $this->requestClient();
        $clientPublicId = $client ? $client->public_id : null;

        //$invoiceItems = $this->requestInvoiceItems();

        $url = '/credits/create/' . $clientPublicId;
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
