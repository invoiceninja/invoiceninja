<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyLedger extends Model
{

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = [
        'id',
    ];

    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    public function company()
    {
    	return $this->belongsTo(Company::class);
    }
}
