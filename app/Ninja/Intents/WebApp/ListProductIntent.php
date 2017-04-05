<?php

namespace App\Ninja\Intents\WebApp;

use App\Ninja\Intents\BaseIntent;

class ListProductIntent extends BaseIntent
{
    public function process()
    {
        $this->loadStates(ENTITY_PRODUCT);

        return redirect('/products');
    }
}
