<?php

namespace App\Ninja\Presenters;

use Laracasts\Presenter\Presenter;
use URL;
use Utils;

class EntityPresenter extends Presenter
{
    /**
     * @return string
     */
    public function url()
    {
        return SITE_URL . $this->path();
    }

    public function path()
    {
        $type = Utils::pluralizeEntityType($this->entity->getEntityType());
        $id = $this->entity->public_id;

        return sprintf('/%s/%s', $type, $id);
    }

    public function editUrl()
    {
        return $this->url() . '/edit';
    }

    public function statusLabel($label = false)
    {
        $class = $text = '';

        if ($this->entity->is_deleted) {
            $class = 'danger';
            $label = trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            $class = 'warning';
            $label = trans('texts.archived');
        } else {
            $class = $this->entity->statusClass();
            $label = $label ?: $this->entity->statusLabel();
        }

        return "<span style=\"font-size:13px\" class=\"label label-{$class}\">{$label}</span>";
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
