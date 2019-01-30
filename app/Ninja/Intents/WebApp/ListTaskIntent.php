<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListTaskIntent extends BaseIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_TASK);

        if ($client = $this->requestClient()) {
            $url = $client->present()->url . '#tasks';
        } else {
            $url = '/tasks';
        }

        return redirect($url);
    }
}
