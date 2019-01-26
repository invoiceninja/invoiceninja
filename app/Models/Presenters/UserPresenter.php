<?php

namespace App\Models\Presenters;

/**
 * Class UserPresenter
 * @package App\Models\Presenters
 */
class UserPresenter extends EntityPresenter
{

    /**
     * @return string
     */
    public function name()
    {
        return $this->entity->first_name . ' ' . $this->entity->last_name;
    }

}
