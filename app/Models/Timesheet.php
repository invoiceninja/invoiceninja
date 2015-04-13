<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Timesheet extends Eloquent
{
    public $timestamps = true;
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function timesheet_events()
    {
        return $this->hasMany('App\Models\TimeSheetEvent');
    }
}
