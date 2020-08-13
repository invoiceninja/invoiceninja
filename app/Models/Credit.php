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

use App\Events\Credit\CreditWasUpdated;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Credit\CreateCreditPdf;
use App\Models\Filterable;
use App\Services\Credit\CreditService;
use App\Services\Ledger\LedgerService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceValues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laracasts\Presenter\PresentableTrait;

class Credit extends BaseModel
{
    use MakesHash;
    use Filterable;
    use MakesDates;
    use SoftDeletes;
    use PresentableTrait;
    use MakesInvoiceValues;

    protected $presenter = 'App\Models\Presenters\CreditPresenter';

    protected $fillable = [
        'assigned_user_id',
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'partial_due_date',
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
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'line_items',
        'client_id',
        'footer',
        'design_id',
        'exchange_rate',
    ];

    protected $casts = [
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_PARTIAL = 3;
    const STATUS_APPLIED = 4;

    public function getEntityType()
    {
        return Credit::class;
    }

    public function getDateAttribute($value)
    {
        if (!empty($value)) {
            //$value format 'Y:m:d H:i:s' to 'Y-m-d H:i'
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }

    public function getDueDateAttribute($value)
    {
        if (!empty($value)) {
            //$value format 'Y:m:d H:i:s' to 'Y-m-d H:i'
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }

    public function getPartialDueDateAttribute($value)
    {
        if (!empty($value)) {
            //$value format 'Y:m:d H:i:s' to 'Y-m-d H:i'
            return (new Carbon($value))->format('Y-m-d');
        }
        return $value;
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }
    
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

    // public function contacts()
    // {
    //     return $this->hasManyThrough(ClientContact::class, Client::class);
    // }

    public function invitations()
    {
        return $this->hasMany(CreditInvitation::class);
    }

    /**
     * The invoice which the credit has been created from
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }
    
    public function ledger()
    {
        return new LedgerService($this);
    }
    /**
     * The invoice/s which the credit has
     * been applied to.
     */
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->using(Paymentable::class);
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }


    /**
     * Access the invoice calculator object
     *
     * @return object The invoice calculator object getters
     */
    public function calc()
    {
        $credit_calc = null;

        if ($this->uses_inclusive_taxes) {
            $credit_calc = new InvoiceSumInclusive($this);
        } else {
            $credit_calc = new InvoiceSum($this);
        }

        return $credit_calc->build();
    }


    public function service()
    {
        return new CreditService($this);
    }

    /**
     * @param float $balance_adjustment
     */
    public function updateBalance($balance_adjustment)
    {
        if ($this->is_deleted) {
            return;
        }

        $balance_adjustment = floatval($balance_adjustment);

        $this->balance = $this->balance + $balance_adjustment;

        if ($this->balance == 0) {
            $this->status_id = self::STATUS_APPLIED;
            $this->save();
            //event(new InvoiceWasPaid($this, $this->company));//todo

            return;
        }

        $this->save();
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->save();
    }

    public function pdf_file_path($invitation = null)
    {

        $storage_path = Storage::url($this->client->credit_filepath() . $this->number . '.pdf');

        if (Storage::exists($this->client->credit_filepath() . $this->number . '.pdf')) {
            return $storage_path;
        }

        if (!$invitation) {
           event(new CreditWasUpdated($this, $this->company, Ninja::eventVars())); 
            CreateCreditPdf::dispatchNow($this, $this->company, $this->client->primary_contact()->first());
        } else {
           event(new CreditWasUpdated($this, $this->company, Ninja::eventVars())); 
            CreateCreditPdf::dispatchNow($invitation->credit, $invitation->company, $invitation->contact);
        }

        return $storage_path;
    }

    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (!isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->save();
            }
        });
    }
}
