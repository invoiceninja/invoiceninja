<?php namespace App\Models;

use Auth;
use Utils;
use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectCode extends Eloquent
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

    public function project()
    {
        return $this->belongsTo('App\Models\Project');
    }

    public function events()
    {
        return $this->hasMany('App\Models\TimesheetEvent');
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
