<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\InvoiceIntent;

class ListQuoteIntent extends InvoiceIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_QUOTE);
        $this->loadStatuses(ENTITY_QUOTE);

        if (! $this->hasField('Filter', 'all') && $client = $this->requestClient()) {
            $url = $client->present()->url . '#quotes';
        } else {
            $url = '/quotes';
        }

        return redirect($url);
    }
}
