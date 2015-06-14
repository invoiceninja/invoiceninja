<?php namespace App\Models;

use DB;

use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends EntityModel
{
    use SoftDeletes;

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }
}

Task::created(function ($task) {
    //Activity::createTask($task);
});

Task::updating(function ($task) {
    //Activity::updateTask($task);
});

Task::deleting(function ($task) {
    //Activity::archiveTask($task);
});

Task::restoring(function ($task) {
    //Activity::restoreTask($task);
});
