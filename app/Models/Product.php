<?php

namespace App\Models;

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    protected $guarded = [
        'id',
        'updated_at',
        'created_at',
        'deleted_at',      
        'q',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }


}
