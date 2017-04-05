<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListCreditIntent extends BaseIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_CREDIT);

        if (! $this->hasField('Filter', 'all') && $client = $this->requestClient()) {
            $url = $client->present()->url . '#credits';
        } else {
            $url = '/credits';
        }

        return redirect($url);
    }
}
