<?php

class Project extends Eloquent
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
    
    public function client()
	{
		return $this->belongsTo('Client');
	}
    
    public function codes()
	{
		return $this->hasMany('ProjectCode');
	}
    
    public static function createNew($parent = false)
	{		
		$className = get_called_class();
		$entity = new $className();
		
		if ($parent)
		{
			$entity->user_id = $parent instanceof User ? $parent->id : $parent->user_id;
			$entity->account_id = $parent->account_id;
		} 
		else if (Auth::check()) 
		{
			$entity->user_id = Auth::user()->id;
			$entity->account_id = Auth::user()->account_id;			
		} 
		else 
		{
			Utils::fatalError();
		}
		
		return $entity;
	}
}

