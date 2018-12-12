<?php

namespace Modules\Notes\Entities;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    protected $guarded = [
    		'id',
    ];

    public function client()
    {
        $this->hasOne(App\Models\Client::class);
    }
    
}
