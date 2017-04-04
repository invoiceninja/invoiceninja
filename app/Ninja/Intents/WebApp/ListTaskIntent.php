<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListTaskIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/tasks');
    }
}
