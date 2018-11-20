<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends BaseModel
{
	protected $guarded = [
		'id',
	];

    protected $appends = ['invoice_id'];

    public function getRouteKeyName()
    {
        return 'invoice_id';
    }

    public function getInvoiceIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function invitations()
    {
        $this->morphMany(Invitation::class, 'inviteable');
    }
}
