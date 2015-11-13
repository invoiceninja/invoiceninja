<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class ClientPresenter extends Presenter {

    public function balance()
    {
        $amount = $this->entity->balance;
        $currencyId = $this->entity->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function paid_to_date()
    {
        $amount = $this->entity->paid_to_date;
        $currencyId = $this->entity->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }
}