<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListExpenseIntent extends BaseIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_EXPENSE);

        return redirect('/expenses');
    }
}
