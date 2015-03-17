<?php namespace App\Models;

use Auth;
use Utils;

class ProjectCode extends Eloquent
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

    public function project()
    {
        return $this->belongsTo('Project');
    }

    public function events()
    {
        return $this->hasMany('TimesheetEvent');
    }

    public static function createNew($parent = false)
    {
        $className = get_called_class();
        $entity = new $className();

        if ($parent) {
            $entity->user_id = $parent instanceof User ? $parent->id : $parent->user_id;
            $entity->account_id = $parent->account_id;
        } elseif (Auth::check()) {
            $entity->user_id = Auth::user()->id;
            $entity->account_id = Auth::user()->account_id;
        } else {
            Utils::fatalError();
        }

        return $entity;
    }
}
