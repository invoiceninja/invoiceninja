<?php

namespace App\Models;

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringInvoice extends BaseModel
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
        $this->morphMany(RecurringInvoiceInvitation::class);
    }
}
