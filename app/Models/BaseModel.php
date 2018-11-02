<?php

namespace App\Models;

use Hashids\Hashids;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /*
    public function setIdAttribute($value)
    {
        $hashids = new Hashids(); //decoded output is _always_ an array.
        $hashed_id_array = $hashids->decode($value);

        $this->attributes['id'] = strtolower($hashed_id_array[0]);
    }
    */
}
