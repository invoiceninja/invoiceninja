<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class CreateTaskIntent extends BaseIntent
{
    public function process()
    {
        $client = $this->requestClient();
        $clientPublicId = $client ? $client->public_id : null;

        //$invoiceItems = $this->requestInvoiceItems();

        $url = '/tasks/create/' . $clientPublicId . '?';
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
