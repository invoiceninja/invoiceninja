<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Events\Invoice\InvoiceReminderWasEmailed;
use App\Events\Invoice\InvoiceWasEmailed;
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

/**
 * App\Models\Invoice
 *
 * @property int $id
 * @property int $client_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int $status_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $recurring_id
 * @property int|null $design_id
 * @property string|null $number
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null $due_date
 * @property bool $is_deleted
 * @property object|null $line_items
 * @property object|null $backup
 * @property string|null $footer
 * @property string|null $public_notes
 * @property string|null $private_notes
 * @property string|null $terms
 * @property string|null $tax_name1
 * @property string $tax_rate1
 * @property string|null $tax_name2
 * @property string $tax_rate2
 * @property string|null $tax_name3
 * @property string $tax_rate3
 * @property string $total_taxes
 * @property int $uses_inclusive_taxes
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $next_send_date
 * @property string|null $custom_surcharge1
 * @property string|null $custom_surcharge2
 * @property string|null $custom_surcharge3
 * @property string|null $custom_surcharge4
 * @property int $custom_surcharge_tax1
 * @property int $custom_surcharge_tax2
 * @property int $custom_surcharge_tax3
 * @property int $custom_surcharge_tax4
 * @property string $exchange_rate
 * @property string $amount
 * @property string $balance
 * @property string|null $partial
 * @property string|null $partial_due_date
 * @property string|null $last_viewed
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $reminder1_sent
 * @property string|null $reminder2_sent
 * @property string|null $reminder3_sent
 * @property string|null $reminder_last_sent
 * @property int $auto_bill_enabled
 * @property string $paid_to_date
 * @property int|null $subscription_id
 * @property int $auto_bill_tries
 * @property int $is_proforma
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read int|null $company_ledger_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read int|null $credits_count
 * @property-read \App\Models\Design|null $design
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Expense|null $expense
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read mixed $balance_due
 * @property-read mixed $hashed_id
 * @property-read mixed $status
 * @property-read mixed $total
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\RecurringInvoice|null $recurring_invoice
 * @property-read \App\Models\Subscription|null $subscription
 * @property-read \App\Models\Task|null $task
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\InvoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereAutoBillEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereAutoBillTries($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereBackup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurcharge1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurcharge2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurcharge3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurcharge4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurchargeTax1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurchargeTax2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurchargeTax3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomSurchargeTax4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereDesignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereFooter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereIsAmountDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereIsProforma($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereLastSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereLastViewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereLineItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereNextSendDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice wherePaidToDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice wherePartial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice wherePartialDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice wherePoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice wherePublicNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereRecurringId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereReminder1Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereReminder2Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereReminder3Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereReminderLastSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTaxName1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTaxName2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTaxName3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTaxRate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTaxRate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTaxRate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTerms($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereTotalTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereUsesInclusiveTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Invoice withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @mixin \Eloquent
 */
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
        'design_id',
        'assigned_user_id',
        'exchange_rate',
        'subscription_id',
        'auto_bill_enabled',
        'uses_inclusive_taxes',
        'vendor_id',
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
        'is_deleted' => 'bool',
        'is_amount_discount' => 'bool',
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

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
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
        return $this->belongsTo(RecurringInvoice::class, 'recurring_id', 'id')->withTrashed();
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
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);
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

    public function task()
    {
        return $this->hasOne(Task::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function expense()
    {
        return $this->hasOne(Expense::class);
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
                return '<h5><span class="badge badge-warning text-white">'.ctrans('texts.unpaid').'</span></h5>';
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

    public function pdf_file_path($invitation = null, string $type = 'path', bool $portal = false)
    {
        if (! $invitation) {
            if ($this->invitations()->exists()) {
                $invitation = $this->invitations()->first();
            } else {
                $this->service()->createInvitations();
                $invitation = $this->invitations()->first();
            }
        }

        if (! $invitation) {
            throw new \Exception('Hard fail, could not create an invitation - is there a valid contact?');
        }

        $file_path = $this->client->invoice_filepath($invitation).$this->numberFormatter().'.pdf';

        $file_exists = false;

        /* Flysystem throws an exception if the path is "corrupted" so lets wrap it in a try catch and return a bool  06/01/2022*/
        try {
            $file_exists = Storage::disk(config('filesystems.default'))->exists($file_path);
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }

        if (Ninja::isHosted() && $portal && $file_exists) {
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        } elseif (Ninja::isHosted()) {
            $file_path = (new CreateEntityPdf($invitation, config('filesystems.default')))->handle();

            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        }

        try {
            $file_exists = Storage::disk(config('filesystems.default'))->exists($file_path);
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }

        if ($file_exists) {
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        }


        try {
            $file_exists = Storage::disk('public')->exists($file_path);
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }

        if ($file_exists) {
            return Storage::disk('public')->{$type}($file_path);
        }

        $file_path = (new CreateEntityPdf($invitation))->handle();

        return Storage::disk('public')->{$type}($file_path);
    }

    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->load('invoice');
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
                return $this->status_id == self::STATUS_SENT;
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

    public function getPayableAmount()
    {
        if ($this->partial > 0) {
            return $this->partial;
        }

        if ($this->balance > 0) {
            return $this->balance;
        }

        if ($this->status_id = 1) {
            return $this->amount;
        }

        return 0;
    }

    public function entityEmailEvent($invitation, $reminder_template, $template = '')
    {
        switch ($reminder_template) {
            case 'invoice':
                event(new InvoiceWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $template));
                break;
            case 'reminder1':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), Activity::INVOICE_REMINDER1_SENT));
                break;
            case 'reminder2':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), Activity::INVOICE_REMINDER2_SENT));
                break;
            case 'reminder3':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), Activity::INVOICE_REMINDER3_SENT));
                break;
            case 'reminder_endless':
            case 'endless_reminder':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), Activity::INVOICE_REMINDER_ENDLESS_SENT));
                break;
            default:
                // code...
                break;
        }
    }

    public function transaction_event()
    {
        $invoice = $this->fresh();

        return [
            'invoice_id' => $invoice->id,
            'invoice_amount' => $invoice->amount ?: 0,
            'invoice_partial' => $invoice->partial ?: 0,
            'invoice_balance' => $invoice->balance ?: 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?: 0,
            'invoice_status' => $invoice->status_id ?: 1,
        ];
    }

    public function expense_documents()
    {
        $line_items = $this->line_items;
            
        $expense_ids = [];

        foreach ($line_items as $item) {
            if (property_exists($item, 'expense_id')) {
                $expense_ids[] = $item->expense_id;
            }
        }
            
        return Expense::whereIn('id', $this->transformKeys($expense_ids))
                           ->where('invoice_documents', 1)
                           ->where('company_id', $this->company_id)
                           ->cursor();
    }

    public function task_documents()
    {
        $line_items = $this->line_items;
            
        $task_ids = [];

        foreach ($line_items as $item) {
            if (property_exists($item, 'task_id')) {
                $task_ids[] = $item->task_id;
            }
        }
            
        return Task::whereIn('id', $this->transformKeys($task_ids))
                           ->whereHas('company', function ($query) {
                               $query->where('invoice_task_documents', 1);
                           })
                           ->where('company_id', $this->company_id)
                           ->cursor();
    }

    public function translate_entity()
    {
        return ctrans('texts.invoice');
    }
}
