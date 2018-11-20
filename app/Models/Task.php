<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends BaseModel
{
    protected $guarded = [
		'id',
	];

    protected $appends = ['task_id'];

    public function getRouteKeyName()
    {
        return 'task_id';
    }

    public function getTaskIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

}
