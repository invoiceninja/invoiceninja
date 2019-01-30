<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class FindClientIntent extends BaseIntent
{
    public function process()
    {
        $client = $this->requestClient();

        $url = $client ? $client->present()->url : '/clients';

        return redirect($url);
    }
}
