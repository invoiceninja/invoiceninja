<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListRecurringInvoiceIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/recurring_invoices');
    }
}
