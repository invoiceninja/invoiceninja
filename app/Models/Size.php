<?php namespace App\Models;

use Eloquent;

class Size extends Eloquent
{
    public $timestamps = false;

    public function getName() 
    {
        return $this->name;
    }    
}
