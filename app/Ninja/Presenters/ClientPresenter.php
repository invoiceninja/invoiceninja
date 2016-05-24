<?php namespace App\Ninja\Presenters;

use URL;
use Utils;

class ClientPresenter extends EntityPresenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function status()
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $text = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $text = trans('texts.archived');
        } else {
            $class = 'success';
            $text = trans('texts.active');
        }

        return "<span class=\"label label-{$class}\">{$text}</span>";
    }
}
