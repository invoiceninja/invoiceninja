<?php namespace App\Ninja\Presenters;

class VendorPresenter extends EntityPresenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }
    
}
