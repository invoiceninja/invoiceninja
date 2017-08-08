<?php

namespace App\Ninja\Intents;

use App\Models\Product;
use Auth;

class ListProductsIntent extends ProductIntent
{
    public function process()
    {
        $account = Auth::user()->account;
        $products = Product::scope()
            ->orderBy('product_key')
            ->limit(5)
            ->get()
            ->transform(function ($item, $key) use ($account) {
                $card = $item->present()->skypeBot($account);
                if ($this->stateEntity(ENTITY_INVOICE)) {
                    $card->addButton('imBack', trans('texts.add_to_invoice', ['invoice' => '']), trans('texts.add_product_to_invoice', ['product' => $item->product_key]));
                }

                return $card;
            });

        return $this->createResponse(SKYPE_CARD_CAROUSEL, $products);
    }
}
