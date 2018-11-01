<?php

namespace App\Models\Presenters;

class CompanyPresenter extends EntityPresenter
{

    public function name()
    {
        return $this->entity->name ?: trans('texts.untitled_account');
    }

}
