<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListClientIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/clients');
    }
}
