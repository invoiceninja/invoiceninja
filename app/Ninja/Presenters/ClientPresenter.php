<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class ClientPresenter extends Presenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }
}