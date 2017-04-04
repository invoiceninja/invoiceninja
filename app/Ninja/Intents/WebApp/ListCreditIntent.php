<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListCreditIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/credits');
    }
}
