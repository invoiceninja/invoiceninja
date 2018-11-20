<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends BaseModel
{
    protected $guarded = [
		'id',
	];

    protected $appends = ['payment_id'];

    public function getRouteKeyName()
    {
        return 'payment_id';
    }

    public function getPaymentIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }
}
