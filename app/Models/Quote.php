<?php

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;

class Quote extends BaseModel
{
    use MakesHash;
    
	protected $guarded = [
		'id',
	];

    const STATUS_DRAFT = 1;
    const STATUS_SENT =  2;
    const STATUS_APPROVED = 4;
    const STATUS_OVERDUE = -1;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invitations()
    {
        return $this->hasMany(QuoteInvitation::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

}
