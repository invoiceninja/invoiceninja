<?php

namespace App\Models\Presenters;

class CompanyPresenter extends EntityPresenter
{

    public function name()
    {
        return $this->entity->name ?: ctrans('texts.untitled_account');
    }

}
