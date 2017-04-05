<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListPaymentIntent extends BaseIntent
{
    public function process()
    {
        if ($client = $this->requestClient()) {
            $url = $client->present()->url . '#payments';
        } else {
            $url = '/payments';
        }

        return redirect($url);
    }
}
