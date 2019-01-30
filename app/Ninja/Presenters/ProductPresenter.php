<?php

namespace App\Ninja\Presenters;

use DropdownButton;
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

    public function moreActions()
    {
        $product = $this->entity;
        $actions = [];

        if (! $product->trashed()) {
            if (auth()->user()->can('create', ENTITY_PRODUCT)) {
                $actions[] = ['url' => 'javascript:submitAction("clone")', 'label' => trans('texts.clone_product')];
            }
            if (auth()->user()->can('create', ENTITY_INVOICE)) {
                $actions[] = ['url' => 'javascript:submitAction("invoice")', 'label' => trans('texts.invoice_product')];
            }
            if (count($actions)) {
                $actions[] = DropdownButton::DIVIDER;
            }
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans("texts.archive_product")];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans("texts.restore_product")];
        }
        if (! $product->is_deleted) {
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans("texts.delete_product")];
        }

        return $actions;
    }

}
