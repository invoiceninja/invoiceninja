<?php

namespace App\Models;

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Payment extends BaseModel
{
    use MakesHash;
    use Filterable;
    
    protected $guarded = [
		'id',
	];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

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
