<?php namespace App\Models;

use Eloquent;

class Country extends Eloquent
{
    public $timestamps = false;
    protected $softDelete = false;

    protected $visible = ['id', 'name'];
}
