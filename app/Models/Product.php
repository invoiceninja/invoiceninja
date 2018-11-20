<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends BaseModel
{
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
