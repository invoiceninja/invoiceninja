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
    const STATUS_VIEWED = 3;
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
        $this->morphMany(Invitation::class, 'inviteable');
    }
}
