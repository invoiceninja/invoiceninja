<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountToken extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }
}
