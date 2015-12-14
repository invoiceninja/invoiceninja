<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;

class AccountPresenter extends Presenter {

    public function name()
    {
        return $this->entity->name ?: trans('texts.untitled_account');
    }

    public function website()
    {
        return Utils::addHttp($this->entity->website);
    }
}