<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class InvoicePresenter extends Presenter {

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function balance_due()
    {
        $amount = $this->entity->getRequestedAmount();
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function status()
    {
        $status = $this->entity->invoice_status ? $this->entity->invoice_status->name : 'draft';
        $status = strtolower($status);
        return trans("texts.status_{$status}");
    }

    public function balance()
    {
        $amount = $this->entity->balance;
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function amount()
    {
        $amount = $this->entity->amount;
        $currencyId = $this->entity->client->currency_id;

        return Utils::formatMoney($amount, $currencyId);
    }

    public function discount()
    {
        if ($this->entity->is_amount_discount) {
            $currencyId = $this->entity->client->currency_id;
            return Utils::formatMoney($this->entity->discount, $currencyId);
        } else {
            return $this->entity->discount . '%';
        }
    }

    public function invoice_date()
    {
        return Utils::fromSqlDate($this->entity->invoice_date);
    }

    public function due_date()
    {
        return Utils::fromSqlDate($this->entity->due_date);
    }

}