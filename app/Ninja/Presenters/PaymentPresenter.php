<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class PaymentPresenter extends Presenter {

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function payment_date()
    {
        return Utils::fromSqlDate($this->entity->payment_date);
    }

    public function amount()
    {
        $amount = $this->entity->amount;
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function method()
    {
        if ($this->entity->account_gateway) {
            return $this->entity->account_gateway->gateway->name;
        } elseif ($this->entity->payment_type) {
            return $this->entity->payment_type->name;
        }
    }

}