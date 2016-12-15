<?php namespace App\Ninja\Presenters;

use Utils;
use URL;
use Laracasts\Presenter\Presenter;

class EntityPresenter extends Presenter
{
    /**
     * @return string
     */
    public function url()
    {
        $type = Utils::pluralizeEntityType($this->entity->getEntityType());
        $id = $this->entity->public_id;
        $link = sprintf('/%s/%s', $type, $id);

        return URL::to($link);
    }

    public function editUrl()
    {
        return $this->url() . '/edit';
    }

    public function statusLabel()
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $text = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $text = trans('texts.archived');
        } else {
            //$class = 'success';
            //$text = trans('texts.active');
        }

        return "<span style=\"font-size:13px\" class=\"label label-{$class}\">{$text}</span>";
    }

    /**
     * @return mixed
     */
    public function link()
    {
        $name = $this->entity->getDisplayName();
        $link = $this->url();

        return link_to($link, $name)->toHtml();
    }

    public function titledName()
    {
        $entity = $this->entity;
        $entityType = $entity->getEntityType();

        return sprintf('%s: %s', trans('texts.' . $entityType), $entity->getDisplayName());
    }
}
