<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends BaseModel
{
    use MakesHash;
    
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
