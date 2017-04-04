<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListVendorIntent extends BaseIntent
{
    public function process()
    {
        return redirect('/vendors');
    }
}
