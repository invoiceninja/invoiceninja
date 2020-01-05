<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
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
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'invoice_type_id',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'is_amount_discount',
        'footer',
        'partial',
        'partial_due_date',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'line_items',
        'client_id',
        'footer',
    ];

    protected $casts = [
        'line_items' => 'object',
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
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }
    
    public function invitations()
    {
        return $this->hasMany(QuoteInvitation::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Access the quote calculator object
     *
     * @return object The quote calculator object getters
     */
    public function calc()
    {
        $quote_calc = null;

        if ($this->uses_inclusive_taxes) {
            $quote_calc = new InvoiceSumInclusive($this);
        } else {
            $quote_calc = new InvoiceSum($this);
        }

        return $quote_calc->build();
    }
}
