<?php

class Timesheet extends Eloquent
{
    public $timestamps = true;
    protected $softDelete = true;

    public function account()
    {
        return $this->belongsTo('Account');
    }

    public function user()
    {
        return $this->belongsTo('User');
    }

    public function timesheet_events()
    {
        return $this->hasMany('TimeSheetEvent');
    }
}
