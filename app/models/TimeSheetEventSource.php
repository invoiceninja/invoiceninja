<?php

class TimeSheetEventSource extends Eloquent
{
	public $timestamps = false;
	protected $softDelete = false;
    
    public function account()
	{
		return $this->belongsTo('Account');
	}

	public function user()
	{
		return $this->belongsTo('User');
	}
}