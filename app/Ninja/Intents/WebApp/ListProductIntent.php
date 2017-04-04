<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListProductIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/products');
    }
}
