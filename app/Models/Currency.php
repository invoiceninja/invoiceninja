<?php namespace App\Models;

use Eloquent;

class Currency extends Eloquent
{
    public $timestamps = false;

    public function getName() 
    {
        return $this->name;
    }    
}
