<?php

namespace App\Models;

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

	protected $guarded = [
		'id',
	];

    protected $casts = [
        'settings' => 'object'
    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT =  2;
    const STATUS_PARTIAL = 5;
    const STATUS_PAID = 6;
    const STATUS_REVERSED = 7; //new for V2

    const STATUS_OVERDUE = -1;
    const STATUS_UNPAID = -2;

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
        return $this->hasMany(InvoiceInvitation::class);
    }
}
