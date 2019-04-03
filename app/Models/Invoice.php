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
