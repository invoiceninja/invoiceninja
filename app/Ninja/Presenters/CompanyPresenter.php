<?php

namespace App\Ninja\Presenters;

class CompanyPresenter extends EntityPresenter
{
    public function promoMessage()
    {
        if (! $this->entity->hasActivePromo()) {
            return '';
        }

        return trans('texts.promo_message', [
            'expires' => $this->entity->promo_expires->format('M dS, Y'),
            'amount' => (int) ($this->discount * 100) . '%',
        ]);
    }

    public function discountMessage()
    {
        if (! $this->entity->hasActiveDiscount()) {
            return '';
        }

        return trans('texts.discount_message', [
            'expires' => $this->entity->discount_expires->format('M dS, Y'),
            'amount' => (int) ($this->discount * 100) . '%',
        ]);
    }
}
