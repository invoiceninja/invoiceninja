<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class InvoicePresenter extends Presenter {

    public function minimumAmountDue()
    {
        $amount = $this->entity->getRequestedAmount();
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function totalAmountDue()
    {
        $amount = $this->entity->balance;
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

}