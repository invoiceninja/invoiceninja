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

use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Entity\CreateEntityPdf;
use App\Models\Presenters\CreditPresenter;
use App\Services\Credit\CreditService;
use App\Services\Ledger\LedgerService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceValues;
use App\Utils\Traits\MakesReminders;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laracasts\Presenter\PresentableTrait;

/**
 * App\Models\Credit
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
 * @property int|null $invoice_id
 * @property string|null $number
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null $due_date
 * @property int $is_deleted
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
 * @property string $paid_to_date
 * @property int|null $subscription_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read int|null $company_ledger_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\CreditFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Credit filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereBackup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurcharge1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurcharge2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurcharge3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurcharge4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurchargeTax1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurchargeTax2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurchargeTax3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomSurchargeTax4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereDesignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereFooter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereIsAmountDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereLastSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereLastViewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereLineItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereNextSendDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit wherePaidToDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit wherePartial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit wherePartialDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit wherePoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit wherePublicNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereRecurringId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereReminder1Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereReminder2Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereReminder3Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereReminderLastSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTaxName1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTaxName2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTaxName3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTaxRate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTaxRate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTaxRate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTerms($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereTotalTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereUsesInclusiveTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Credit withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Credit withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @mixin \Eloquent
 */
class Credit extends BaseModel
{
    use MakesHash;
    use Filterable;
    use MakesDates;
    use SoftDeletes;
    use PresentableTrait;
    use MakesInvoiceValues;
    use MakesReminders;

    protected $presenter = CreditPresenter::class;

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
        'is_amount_discount' => 'bool',

    ];

    protected $touches = [];

    const STATUS_DRAFT = 1;

    const STATUS_SENT = 2;

    const STATUS_PARTIAL = 3;

    const STATUS_APPLIED = 4;

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

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);
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

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * The invoice which the credit has been created from.
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
     * Access the invoice calculator object.
     *
     * @return stdClass The invoice calculator object getters
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
            $this->saveQuietly();

            return;
        }

        $this->saveQuietly();
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->saveQuietly();
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

        $file_path = $this->client->credit_filepath($invitation).$this->numberFormatter().'.pdf';

        if (Ninja::isHosted() && $portal && Storage::disk(config('filesystems.default'))->exists($file_path)) {
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        } elseif (Ninja::isHosted() && $portal) {
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


        if (Storage::disk('public')->exists($file_path)) {
            return Storage::disk('public')->{$type}($file_path);
        }

        $file_path = (new CreateEntityPdf($invitation))->handle();

        return Storage::disk('public')->{$type}($file_path);
    }

    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->saveQuietly();
            }
        });
    }

    public function transaction_event()
    {
        $credit = $this->fresh();

        return [
            'credit_id' => $credit->id,
            'credit_amount' => $credit->amount ?: 0,
            'credit_balance' => $credit->balance ?: 0,
            'credit_status' => $credit->status_id ?: 1,
        ];
    }

    public function translate_entity()
    {
        return ctrans('texts.credit');
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
            case self::STATUS_APPLIED:
                return ctrans('texts.applied');
                break;
            default:
                return '';
                break;
        }
    }
}
