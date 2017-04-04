<?php

namespace App\Ninja\Intents\WebApp;

use App\Models\EntityModel;
use App\Ninja\Intents\InvoiceIntent;
use Exception;

class CreateInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $client = $this->requestClient();
        $invoiceItems = $this->requestInvoiceItems();

        if (! $client) {
            throw new Exception(trans('texts.client_not_found'));
        }

        $data = array_merge($this->requestFields(), [
            'client_id' => $client->public_id,
            'invoice_items' => $invoiceItems,
        ]);

        //var_dump($data);
        dd($data);
    }
}
