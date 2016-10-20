<?php namespace App\Ninja\Presenters;

use URL;
use Laracasts\Presenter\Presenter;

class EntityPresenter extends Presenter
{
    /**
     * @return string
     */
    public function url()
    {
        $type = $this->entity->getEntityType();
        $id = $this->entity->public_id;
        $link = sprintf('/%ss/%s', $type, $id);

        return URL::to($link);
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
