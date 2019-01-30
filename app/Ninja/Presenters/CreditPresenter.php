<?php

namespace App\Ninja\Presenters;

use Utils;

/**
 * Class CreditPresenter.
 */
class CreditPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    /**
     * @return \DateTime|string
     */
    public function credit_date()
    {
        return Utils::fromSqlDate($this->entity->credit_date);
    }
}
