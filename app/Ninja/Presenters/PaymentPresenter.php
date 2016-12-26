<?php namespace App\Ninja\Presenters;

use Utils;

class PaymentPresenter extends EntityPresenter {

    public function amount()
    {
        return Utils::formatMoney($this->entity->amount, $this->entity->client->currency_id);
    }

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function payment_date()
    {
        return Utils::fromSqlDate($this->entity->payment_date);
    }

    public function method()
    {
        if ($this->entity->account_gateway) {
            return $this->entity->account_gateway->gateway->name;
        } elseif ($this->entity->payment_type) {
            return $this->entity->payment_type->name;
        }
    }

    public function statusLabel()
    {
        if ($label = parent::statusLabel()) {
            return $label;
        }

        $class = $this->entity->statusClass();
        $label = $this->entity->statusLabel();

        return "<span style=\"font-size:13px\" class=\"label label-{$class}\">{$label}</span>";
    }
}
