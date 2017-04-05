<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\InvoiceIntent;

class ListInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        if ($client = $this->requestClient()) {
            $url = $client->present()->url . '#invoices';
        } else {
            $url = '/invoices';
        }

        return redirect($url);
    }
}
