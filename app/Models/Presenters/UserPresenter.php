<?php

namespace App\models\Presenters;

class UserPresenter extends EntityPresenter
{

    public function name()
    {
        return $this->entity->first_name . ' ' . $this->entity->last_name;
    }

}
