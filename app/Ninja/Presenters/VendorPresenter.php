<?php namespace App\Ninja\Presenters;

use Utils;
use Laracasts\Presenter\Presenter;
// vendor
class VendorPresenter extends Presenter {

    public function country()
    {
        return $this->entity->country ? $this->entity->country->name : '';
    }

    public function link()
    {
        return link_to('/vendors/' . $this->entity->public_id, $this->entity->name);
    }
}