<?php

class TimesheetEvent extends Eloquent
{
	public $timestamps = true;
	protected $softDelete = true;
    
    /* protected $dates = array('org_updated_at');
    
    public function getDates() {
        return array('created_at', 'updated_at', 'deleted_at');
    } */

    /* public function setOrgUpdatedAtAttribute($value)
    {
        var_dump($value);
        $this->attributes['org_updated_at'] = $value->getTimestamp();
    }*/
    
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
		return $this->belongsTo('TimesheetEventSource');
	}
    
    public function timesheet()
	{
		return $this->belongsTo('Timesheet');
	}	

	public function project()
	{
		return $this->belongsTo('Project');
	}
    
    public function project_code()
	{
		return $this->belongsTo('ProjectCode');
	}
    
    /**
     * @return TimesheetEvent
     */
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
    
    public function toChangesArray(TimesheetEvent $other)
    {
        $attributes_old = parent::toArray();
        $attributes_new = $other->toArray();

        $skip_keys = ['id' => 1, 'created_at' => 1, 'updated_at' => 1, 'deleted_at' => 1, 'org_data' => 1, 'update_data' => 1];
        $zeroisempty_keys = ['discount' => 1];
        
        $result = [];
        // Find all the values that where changed or deleted
        foreach ($attributes_old as $key => $value) {
            // Skip null values, keys we don't care about and 0 value keys that means they are not used
            if(empty($value) || isset($skip_keys[$key])|| (isset($zeroisempty_keys[$key]) && $value) ) {
                continue;
            }
            
            // Compare values if it exists in the new array
            if(isset($attributes_new[$key]) || array_key_exists($key, $attributes_new)) {
                if($value instanceof \DateTime && $attributes_new[$key] instanceof \DateTime) {
                    if($value != $attributes_new[$key]) {
                        $result[$key] = $attributes_new[$key]->format("Y-m-d H:i:s");
                    }
                } elseif($value instanceof \DateTime && is_string($attributes_new[$key])) {
                    if($value->format("Y-m-d H:i:s") != $attributes_new[$key]) {
                        $result[$key] = $attributes_new[$key];
                    }
                } elseif(is_string($value) && $attributes_new[$key] instanceof \DateTime) {
                    if($attributes_new[$key]->format("Y-m-d H:i:s") != $value) {
                        $result[$key] = $attributes_new[$key]->format("Y-m-d H:i:s");
                    }
                } elseif($value != $attributes_new[$key]) {
                     $result[$key] = $attributes_new[$key];
                }
                
            } else {
                $result[$key] = null;
            }
        }
        
        // Find all the values that where deleted
        foreach ($attributes_new as $key => $value) {
            if(isset($skip_keys[$key])) {
                continue;
            }
            
            if(!isset($attributes_old[$key])) {
                 $result[$key] = $value;
             }
        }
        
        return $result;
    }
    
}
