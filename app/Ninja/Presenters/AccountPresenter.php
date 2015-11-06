<?php namespace App\Ninja\Presenters;

use Laracasts\Presenter\Presenter;

class AccountPresenter extends Presenter {

    public function name()
    {
        return $this->entity->name ?: trans('texts.untitled_account');
    }

}