<?php

namespace App\Ninja\Presenters;

use App\Libraries\Skype\HeroCard;

class ProductPresenter extends EntityPresenter
{
    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function skypeBot($account)
    {
        $product = $this->entity;

        $card = new HeroCard();
        $card->setTitle($product->product_key);
        $card->setSubitle($account->formatMoney($product->cost));
        $card->setText($product->notes);

        return $card;
    }
}
