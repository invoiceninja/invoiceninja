<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\InvoiceIntent;

class FindInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->requestInvoice();

        $url = $invoice ? $invoice->present()->url : '/invoices';

        return redirect($url);
    }
}
