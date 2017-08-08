<?php

namespace App\Ninja\Presenters;

class UserPresenter extends EntityPresenter
{
    public function email()
    {
        return htmlentities(sprintf('%s <%s>', $this->fullName(), $this->entity->email));
    }

    public function fullName()
    {
        return $this->entity->first_name . ' ' . $this->entity->last_name;
    }
}
