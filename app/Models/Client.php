<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

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
