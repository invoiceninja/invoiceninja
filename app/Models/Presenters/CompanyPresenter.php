<?php

namespace App\Models\Presenters;

/**
 * Class CompanyPresenter
 * @package App\Models\Presenters
 */
class CompanyPresenter extends EntityPresenter
{

    /**
     * @return string
     */
    public function name()
    {
        return $this->entity->name ?: ctrans('texts.untitled_account');
    }

}
