<?php

namespace App\Models\Presenters;

class ClientPresenter extends EntityPresenter
{

    public function name()
    {
        return $this->entity->name ?: $this->entity->primary_contact->first()->first_name . ' '. $this->entity->primary_contact->first()->last_name;
    }
}
