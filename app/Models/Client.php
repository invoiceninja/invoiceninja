<?php

namespace App\Models;

use Laracasts\Presenter\PresentableTrait;

class Client extends BaseModel
{
    use PresentableTrait;

    protected $presenter = 'App\Models\Presenters\ClientPresenter';


    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function locations()
    {
        return $this->hasMany(ClientLocation::class);
    }

    public function primary_location()
    {
        return $this->hasMany(ClientLocation::class)->whereIsPrimary(true);
    }

    public function primary_contact()
    {
        return $this->hasMany(ClientContact::class)->whereIsPrimary(true);
    }

}
