<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListQuotesIntent extends BaseIntent
{
    public function process()
    {
        if ($client = $this->requestClient()) {
            $url = $client->present()->url . '#quotes';
        } else {
            $url = '/quotes';
        }

        return redirect($url);
    }
}
