<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class CreateClientIntent extends BaseIntent
{
    public function process()
    {
        $url = '/clients/create';

        //$url = '/invoices/create/' . $clientPublicId . '?';
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
