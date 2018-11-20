<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxRate extends BaseModel
{
    protected $guarded = [
		'id',
	];

    protected $appends = ['tax_rate_id'];

    public function getRouteKeyName()
    {
        return 'tax_rate_id';
    }

    public function getTaxRateIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

}
