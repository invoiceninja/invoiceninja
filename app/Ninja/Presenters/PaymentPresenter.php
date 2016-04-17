<?php namespace App\Ninja\Presenters;

use URL;
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

    public function method()
    {
        if ($this->entity->account_gateway) {
            return $this->entity->account_gateway->gateway->name;
        } elseif ($this->entity->payment_type) {
            return $this->entity->payment_type->name;
        }
    }

    public function url()
    {
        return URL::to('/payments/' . $this->entity->public_id . '/edit');
    }

    public function link()
    {
        return link_to('/payments/' . $this->entity->public_id . '/edit', $this->entity->getDisplayName());
    }

}