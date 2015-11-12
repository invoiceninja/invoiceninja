<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class CreditPresenter extends Presenter {

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function credit_date()
    {
        return Utils::fromSqlDate($this->entity->credit_date);
    }

    public function amount()
    {
        $amount = $this->entity->amount;
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function balance()
    {
        $amount = $this->entity->balance;
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }


}