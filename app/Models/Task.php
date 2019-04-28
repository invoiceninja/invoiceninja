<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Task extends BaseModel
{
    use MakesHash;
    
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

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

}
