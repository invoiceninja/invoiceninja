<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\InvoiceIntent;

class ListInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        return redirect('/invoices');
    }
}
