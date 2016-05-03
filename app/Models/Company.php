<?php namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Eloquent
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    
    public function accounts()
    {
        return $this->hasMany('App\Models\Account');
    }
    
    public function payment()
    {
        return $this->belongsTo('App\Models\Payment');
    }
}
