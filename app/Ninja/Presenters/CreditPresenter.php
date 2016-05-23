<?php namespace App\Ninja\Presenters;

use Utils;

class CreditPresenter extends EntityPresenter {

    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function credit_date()
    {
        return Utils::fromSqlDate($this->entity->credit_date);
    }
}
