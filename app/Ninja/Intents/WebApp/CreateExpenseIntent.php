<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class CreateExpenseIntent extends BaseIntent
{
    public function process()
    {
        $url = '/expenses/create';

        //$url = '/invoices/create/' . $clientPublicId . '?';
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
