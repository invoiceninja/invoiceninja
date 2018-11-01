<?php

namespace App\Models\Presenters;

class UserPresenter extends EntityPresenter
{

    public function name()
    {
        return $this->entity->first_name . ' ' . $this->entity->last_name;
    }

}
