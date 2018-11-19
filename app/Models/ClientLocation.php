<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientLocation extends BaseModel
{
    public $timestamps = false;

    protected $appends = ['client_location_id'];

    public function getRouteKeyName()
    {
        return 'client_location_id';
    }

    public function getClientLocationIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function client()
    {
    	return $this->belongsTo(Client::class);
    }
}
