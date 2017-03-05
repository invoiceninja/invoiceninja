<?php

namespace App\Ninja\Presenters;

class VendorPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }
}
