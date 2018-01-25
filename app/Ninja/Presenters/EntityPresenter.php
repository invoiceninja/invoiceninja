<?php

namespace App\Ninja\Presenters;

use Laracasts\Presenter\Presenter;
use URL;
use Utils;
use stdClass;

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

        if (! $this->entity->id) {
            return '';
        } elseif ($this->entity->is_deleted) {
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

    public function statusColor()
    {
        $class = $this->entity->statusClass();

        switch ($class) {
            case 'success':
                return '#5cb85c';
            case 'warning':
                return '#f0ad4e';
            case 'primary':
                return '#337ab7';
            case 'info':
                return '#5bc0de';
            default:
                return '#777';
        }
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

    public function calendarEvent($subColors = false)
    {
        $entity = $this->entity;

        $data = new stdClass();
        $data->id = $entity->getEntityType() . ':' . $entity->public_id;
        $data->allDay = true;
        $data->url = $this->url();

        return $data;
    }

}
