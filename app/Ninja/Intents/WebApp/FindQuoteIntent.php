<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\InvoiceIntent;

class FindQuoteIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->requestInvoice();

        $url = $invoice ? $invoice->present()->url : '/quotes';

        return redirect($url);
    }
}
