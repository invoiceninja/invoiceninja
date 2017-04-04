<?php

namespace App\Ninja\Intents\WebApp;

use App\Models\Product;
use App\Models\EntityModel;
use App\Ninja\Intents\ProductIntent;
use Exception;

class CreateProductIntent extends ProductIntent
{
    public function process()
    {
        $url = '/products/create';
        
        //$url = '/invoices/create/' . $clientPublicId . '?';
        //$url .= $this->requestFieldsAsString(Invoice::$requestFields);

        return redirect($url);
    }
}
