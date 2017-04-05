<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\InvoiceIntent;

class ListInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_INVOICE);
        $this->loadStatuses(ENTITY_INVOICE);

        if (! $this->hasField('Filter', 'all') && $client = $this->requestClient()) {
            $url = $client->present()->url . '#invoices';
        } else {
            $url = '/invoices';
        }

        return redirect($url);
    }
}
