<?php namespace App\Ninja\Intents;

use Auth;
use Exception;
use App\Models\Product;


class ListProductsIntent extends ProductIntent
{
    public function process()
    {
        $products = Product::scope()->get();

        return view('bots.skype.list', [
                'items' => $products
            ])->render();

    }
}
