<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Invoice extends BaseModel
{
    use MakesHash;
    
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
