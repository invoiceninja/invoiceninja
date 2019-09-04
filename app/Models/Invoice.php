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

use App\Models\Currency;
use App\Models\Filterable;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesInvoiceValues;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laracasts\Presenter\PresentableTrait;

class Invoice extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use NumberFormatter;
    use MakesDates;
    use PresentableTrait;
    use MakesInvoiceValues;
    protected $presenter = 'App\Models\Presenters\InvoicePresenter';

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'client_id',
        'company_id',
        'backup',
        'settings',
    ];

    protected $fillable = [
        'invoice_number',
        'discount',
        'po_number',
        'invoice_date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'invoice_type_id',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'is_amount_discount',
        'invoice_footer',
        'partial',
        'partial_due_date',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'custom_taxes1',
        'custom_taxes2',
        'custom_text_value1',
        'custom_text_value2',
        'line_items',
        'settings',
        'client_id',
        'footer',
    ];

    protected $casts = [
        'settings' => 'object',
        'line_items' => 'object'
    ];

    protected $with = [
        'company',
        'client',
    ];

    protected $appends = [
        'hashed_id',
        'status'
    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_PARTIAL = 3;
    const STATUS_PAID = 4;
    const STATUS_CANCELLED = 5;

    const STATUS_OVERDUE = -1;
    const STATUS_UNPAID = -2;
    const STATUS_REVERSED = -3;

    
    public function getStatusAttribute()
    {

        if($this->status_id == Invoice::STATUS_SENT && $this->due_date > Carbon::now())
            return Invoice::STATUS_UNPAID;
        else if($this->status_id == Invoice::STATUS_PARTIAL && $this->partial_due_date > Carbon::now())
            return Invoice::STATUS_UNPAID;
        else if($this->status_id == Invoice::STATUS_SENT && $this->due_date < Carbon::now())
            return Invoice::STATUS_OVERDUE;
        else if($this->status_id == Invoice::STATUS_PARTIAL && $this->partial_due_date < Carbon::now())
            return Invoice::STATUS_OVERDUE;
        else
            return $this->status_id;

    }
    
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    /* ---------------- */
    /* Settings getters */
    /* ---------------- */

    /**
     * If True, prevents an invoice from being 
     * modified once it has been marked as sent
     * 
     * @return boolean isLocked
     */
    public function isLocked() : bool
    {
        return $this->client->getMergedSettings()->lock_sent_invoices;
    }

    /**
     * Gets the currency from the settings object.
     *
     * @return     Eloquent Model  The currency.
     */
    public function getCurrency()
    {
        return Currency::find($this->settings->currency_id);
    }


    /**
     * Determines if invoice overdue.
     *
     * @param      float    $balance   The balance
     * @param      date.    $due_date  The due date
     *
     * @return     boolean  True if overdue, False otherwise.
     */
    public static function isOverdue($balance, $due_date)
    {
        if (! $this->formatValue($balance,2) > 0 || ! $due_date) {
            return false;
        }

        // it isn't considered overdue until the end of the day
        return strtotime($this->createClientDate(date(), $this->client->timezone()->name)) > (strtotime($due_date) + (60 * 60 * 24));
    }

    public function markViewed()
    {
        $this->last_viewed = Carbon::now()->format('Y-m-d H:i');
    }
    
    public function isPayable()
    {
        return ($this->status === Invoice::STATUS_UNPAID || $this->status === Invoice::STATUS_OVERDUE);
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case Invoice::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
                break;
            case Invoice::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
                break;
            case Invoice::STATUS_PARTIAL:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.partial').'</span></h5>';
                break;
            case Invoice::STATUS_PAID:
                return '<h5><span class="badge badge-success">'.ctrans('texts.paid').'</span></h5>';
                break;
            case Invoice::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
                break;
            case Invoice::STATUS_OVERDUE:
                return '<h5><span class="badge badge-danger">'.ctrans('texts.overdue').'</span></h5>';
                break;
            case Invoice::STATUS_UNPAID:
                return '<h5><span class="badge badge-warning">'.ctrans('texts.unpaid').'</span></h5>';
                break;      
            case Invoice::STATUS_REVERSED:
                return '<h5><span class="badge badge-info">'.ctrans('texts.reversed').'</span></h5>';
                break;           
            default:
                # code...
                break;
        }
    }

    /**
     * Returns the template for the invoice
     * 
     * @return string Either the template view, OR the template HTML string
     */
    public function design() :string
    {
        return File::exists(resource_path($this->settings->design)) ? File::get(resource_path($this->settings->design)) : File::get(resource_path('views/pdf/design1.blade.php'));
    }

}