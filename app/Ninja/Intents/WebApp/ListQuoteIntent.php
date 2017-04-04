<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListQuotesIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/quotes');
    }
}
