<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends BaseModel
{
    use MakesHash;
    use Filterable;
    use SoftDeletes;
    
	protected $fillable = [
		'client_id',
        'quote_number',
        'discount',
        'is_amount_discount',
        'po_number',
        'quote_date',
        'valid_until',
        'line_items',
        'settings',
        'footer',
        'public_note',
        'private_notes',
        'terms',
        'tax_name1',
        'tax_name2',
        'tax_name3',
        'tax_rate1',
        'tax_rate2',
        'tax_rate3',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'amount',
        'partial',
        'partial_due_date',
	];

    protected $casts = [
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];
    
    const STATUS_DRAFT = 1;
    const STATUS_SENT =  2;
    const STATUS_APPROVED = 3;
    const STATUS_EXPIRED = -1;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }


    public function assigned_user()
    {
        return $this->belongsTo(User::class ,'assigned_user_id', 'id')->withTrashed();
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
