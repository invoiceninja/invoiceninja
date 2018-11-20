<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Product extends BaseModel
{
    use MakesHash;
    
    protected $guarded = [
		'id',
	];

    protected $appends = ['product_id'];

    public function getRouteKeyName()
    {
        return 'product_id';
    }

    public function getProductIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }
}
