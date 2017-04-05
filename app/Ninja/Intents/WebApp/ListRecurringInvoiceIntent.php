<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListRecurringInvoiceIntent extends BaseIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_RECURRING_INVOICE);

        if ($client = $this->requestClient()) {
            $url = $client->present()->url . '#recurring_invoices';
        } else {
            $url = '/recurring_invoices';
        }

        return redirect($url);
    }
}
