<?php namespace App\Models;

use Eloquent;

class Language extends Eloquent
{
    public $timestamps = false;

    public function getName() 
    {
        return $this->name;
    }
}
