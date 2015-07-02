<?php namespace App\Models;

use Eloquent;

class Industry extends Eloquent
{
    public $timestamps = false;

    public function getName() 
    {
        return $this->name;
    }
}
