<?php

class TimeSheetEvent extends Eloquent
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
    
    public function source()
	{
		return $this->belongsTo('TimeSheetEventSource');
	}
    
    public function timesheet()
	{
		return $this->belongsTo('TimeSheet');
	}	

	public function project()
	{
		return $this->belongsTo('Project');
	}
    
    public function project_code()
	{
		return $this->belongsTo('ProjectCode');
	}
}
