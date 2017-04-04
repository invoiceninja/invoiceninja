<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListExpenseIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/expenses');
    }
}
