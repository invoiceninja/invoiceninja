<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Events\Invoice\InvoiceReminderWasEmailed;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Entity\CreateEntityPdf;
use App\Models\Presenters\InvoicePresenter;
use App\Services\Invoice\InvoiceService;
use App\Services\Ledger\LedgerService;
use App\Utils\Ninja;
use App\Utils\Traits\Invoice\ActionsInvoice;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesInvoiceValues;
use App\Utils\Traits\MakesReminders;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
    use MakesReminders;
    use ActionsInvoice;

    protected $presenter = InvoicePresenter::class;

    protected $touches = [];

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'client_id',
        'company_id',
    ];

    protected $fillable = [
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'is_amount_discount',
        'partial',
        'partial_due_date',
        'project_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'line_items',
        'client_id',
        'footer',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
    // 'custom_surcharge_tax1',
    // 'custom_surcharge_tax2',
    // 'custom_surcharge_tax3',
    // 'custom_surcharge_tax4',
        'design_id',
        'assigned_user_id',
        'exchange_rate',
        'subscription_id',
    ];

    protected $casts = [
        // 'date' => 'date:Y-m-d',
        // 'due_date' => 'date:Y-m-d',
        // 'partial_due_date' => 'date:Y-m-d',
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $with = [];

    protected $appends = [
        'hashed_id',
        'status',
    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_PARTIAL = 3;
    const STATUS_PAID = 4;
    const STATUS_CANCELLED = 5;
    const STATUS_REVERSED = 6;

    const STATUS_OVERDUE = -1; //status < 4 || < 3 && !is_deleted && !trashed() && due_date < now()
    const STATUS_UNPAID = -2; //status < 4 || < 3 && !is_deleted && !trashed()

    public function getEntityType()
    {
        return self::class;
    }

    public function getDateAttribute($value)
    {
        return $this->dateMutator($value);
    }

    public function getDueDateAttribute($value)
    {
        return $this->dateMutator($value);
    }

    public function getPartialDueDateAttribute($value)
    {
        return $this->dateMutator($value);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function recurring_invoice()
    {
        return $this->belongsTo(RecurringInvoice::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function invitations()
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable')->withTrashed()->withPivot('amount', 'refunded')->withTimestamps();
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(300);
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function credits()
    {
        return $this->hasMany(Credit::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    /**
     * Service entry points.
     */
    public function service() :InvoiceService
    {
        return new InvoiceService($this);
    }

    public function ledger()
    {
        return new LedgerService($this);
    }

    /* ---------------- */
    /* Settings getters */
    /* ---------------- */

    public function getStatusAttribute()
    {
        $due_date = $this->due_date ? Carbon::parse($this->due_date) : false;
        $partial_due_date = $this->partial_due_Date ? Carbon::parse($this->partial_due_date) : false;

        if ($this->status_id == self::STATUS_SENT && $due_date && $due_date->gt(now())) {
            return self::STATUS_UNPAID;
        } elseif ($this->status_id == self::STATUS_PARTIAL && $partial_due_date && $partial_due_date->gt(now())) {
            return self::STATUS_PARTIAL;
        } elseif ($this->status_id == self::STATUS_SENT && $due_date && $due_date->lt(now())) {
            return self::STATUS_OVERDUE;
        } elseif ($this->status_id == self::STATUS_PARTIAL && $partial_due_date && $partial_due_date->lt(now())) {
            return self::STATUS_OVERDUE;
        } else {
            return $this->status_id;
        }
    }

    public function isPayable(): bool
    {

        if ($this->status_id == self::STATUS_DRAFT && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_SENT && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_PARTIAL && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_SENT && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_DRAFT && $this->is_deleted == false) {
            return true;
        } else {
            return false;
        }
    }

    public function isRefundable(): bool
    {
        if ($this->is_deleted) {
            return false;
        }

        if (($this->amount - $this->balance) == 0) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isPartial(): bool
    {
        return $this->status_id >= self::STATUS_PARTIAL;
    }

    /**
     * @return bool
     */
    public function hasPartial(): bool
    {
        return ($this->partial && $this->partial > 0) === true;
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
                break;
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
                break;
            case self::STATUS_PARTIAL:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.partial').'</span></h5>';
                break;
            case self::STATUS_PAID:
                return '<h5><span class="badge badge-success">'.ctrans('texts.paid').'</span></h5>';
                break;
            case self::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
                break;
            case self::STATUS_OVERDUE:
                return '<h5><span class="badge badge-danger">'.ctrans('texts.overdue').'</span></h5>';
                break;
            case self::STATUS_UNPAID:
                return '<h5><span class="badge badge-warning">'.ctrans('texts.unpaid').'</span></h5>';
                break;
            case self::STATUS_REVERSED:
                return '<h5><span class="badge badge-info">'.ctrans('texts.reversed').'</span></h5>';
                break;
            default:
                // code...
                break;
        }
    }

    public static function stringStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return ctrans('texts.draft');
                break;
            case self::STATUS_SENT:
                return ctrans('texts.sent');
                break;
            case self::STATUS_PARTIAL:
                return ctrans('texts.partial');
                break;
            case self::STATUS_PAID:
                return ctrans('texts.paid');
                break;
            case self::STATUS_CANCELLED:
                return ctrans('texts.cancelled');
                break;
            case self::STATUS_OVERDUE:
                return ctrans('texts.overdue');
                break;
            case self::STATUS_UNPAID:
                return ctrans('texts.unpaid');
                break;
            case self::STATUS_REVERSED:
                return ctrans('texts.reversed');
                break;
            default:
                // code...
                break;
        }
    }

    /**
     * Access the invoice calculator object.
     *
     * @return stdClass The invoice calculator object getters
     */
    public function calc()
    {
        $invoice_calc = null;

        if ($this->uses_inclusive_taxes) {
            $invoice_calc = new InvoiceSumInclusive($this);
        } else {
            $invoice_calc = new InvoiceSum($this);
        }

        return $invoice_calc->build();
    }

    public function pdf_file_path($invitation = null, string $type = 'url')
    {
        if (! $invitation) {

            if($this->invitations()->exists())
                $invitation = $this->invitations()->first();
            else{
                $this->service()->createInvitations();
                $invitation = $this->invitations()->first();
            }

        }

        if(!$invitation)
            throw new \Exception('Hard fail, could not create an invitation - is there a valid contact?');

        $storage_path = Storage::$type($this->client->invoice_filepath().$this->numberFormatter().'.pdf');

        if (! Storage::exists($this->client->invoice_filepath().$this->numberFormatter().'.pdf')) {
            event(new InvoiceWasUpdated($this, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
            CreateEntityPdf::dispatchNow($invitation);
        }

        return $storage_path;
    }

    /**
     * Updates Invites to SENT.
     */
    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->save();
            }
        });
    }

    /**
     * Filtering logic to determine
     * whether an invoice is locked
     * based on the current status of the invoice.
     * @return bool [description]
     */
    public function isLocked() :bool
    {
        $locked_status = $this->client->getSetting('lock_invoices');

        switch ($locked_status) {
            case 'off':
                return false;
                break;
            case 'when_sent':
                return $this->status_id == self::STATUS_DRAFT;
                break;
            case 'when_paid':
                return $this->status_id == self::STATUS_PAID || $this->status_id == self::STATUS_PARTIAL;
                break;
            default:
                return false;
                break;
        }
    }

    public function getBalanceDueAttribute()
    {
        return $this->balance;
    }

    public function getTotalAttribute()
    {
        return $this->calc()->getTotal();
    }

    public function entityEmailEvent($invitation, $reminder_template, $template)
    {
        switch ($reminder_template) {
            case 'invoice':
                event(new InvoiceWasEmailed($invitation, $invitation->company, Ninja::eventVars(), $template));
                break;
            case 'reminder1':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(), Activity::INVOICE_REMINDER1_SENT));
                break;
            case 'reminder2':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(), Activity::INVOICE_REMINDER2_SENT));
                break;
            case 'reminder3':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(), Activity::INVOICE_REMINDER3_SENT));
                break;
            case 'reminder_endless':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(), Activity::INVOICE_REMINDER_ENDLESS_SENT));
                break;
            default:
                # code...
                break;
        }
    }
}
