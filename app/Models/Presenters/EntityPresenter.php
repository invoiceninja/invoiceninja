<?php

namespace App\Models\Presenters;

use App\Utils\Traits\MakesHash;
use Hashids\Hashids;
use Laracasts\Presenter\Presenter;
use URL;
use Utils;
use stdClass;

class EntityPresenter extends Presenter
{
    use MakesHash;

    public function id()
    {
        return $this->encodePrimaryKey($this->entity->id);
    }

    public function url()
    {

    }

    public function path()
    {

    }

    public function editUrl()
    {
    }

    public function statusLabel($label = false)
    {

    }

    public function statusColor()
    {

    }

    public function link()
    {

    }

    public function titledName()
    {

    }

    public function calendarEvent($subColors = false)
    {

    }

}
