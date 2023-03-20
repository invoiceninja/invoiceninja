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
use App\Models\Presenters\QuotePresenter;
use App\Services\Quote\QuoteService;
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
 * App\Models\Quote
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
 * @property string|null $next_send_date
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $balance_due
 * @property-read mixed $hashed_id
 * @property-read mixed $total
 * @property-read mixed $valid_until
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\QuoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Quote filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Quote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Quote onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Quote query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereBackup($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurcharge1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurcharge2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurcharge3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurcharge4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurchargeTax1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurchargeTax2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurchargeTax3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomSurchargeTax4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereDesignId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereFooter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereIsAmountDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereLastSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereLastViewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereLineItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereNextSendDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote wherePaidToDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote wherePartial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote wherePartialDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote wherePoNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote wherePublicNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereRecurringId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereReminder1Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereReminder2Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereReminder3Sent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereReminderLastSent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTaxName1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTaxName2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTaxName3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTaxRate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTaxRate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTaxRate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTerms($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereTotalTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereUsesInclusiveTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Quote withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Quote withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteInvitation> $invitations
 * @mixin \Eloquent
 */
class Quote extends BaseModel
{
    use MakesHash;
    use MakesDates;
    use Filterable;
    use SoftDeletes;
    use MakesReminders;
    use PresentableTrait;
    use MakesInvoiceValues;

    protected $presenter = QuotePresenter::class;

    protected $touches = [];

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
        'is_deleted' => 'boolean',
        'is_amount_discount' => 'bool',
    ];

    const STATUS_DRAFT = 1;

    const STATUS_SENT = 2;

    const STATUS_APPROVED = 3;

    const STATUS_CONVERTED = 4;

    const STATUS_EXPIRED = -1;

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

    public function getStatusIdAttribute($value)
    {
        if ($this->due_date && ! $this->is_deleted && $value == self::STATUS_SENT && Carbon::parse($this->due_date)->lte(now()->startOfDay())) {
            return self::STATUS_EXPIRED;
        }

        return $value;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
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

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
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
     * Access the quote calculator object.
     *
     * @return stdClass The quote calculator object getters
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

    /**
     * Updates Invites to SENT.
     */
    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->saveQuietly();
            }
        });
    }

    public function service(): QuoteService
    {
        return new QuoteService($this);
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

        $file_path = $this->client->quote_filepath($invitation).$this->numberFormatter().'.pdf';

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

    /**
     * @param int $status
     * @return string
     */
    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
                break;
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.pending').'</span></h5>';
                break;
            case self::STATUS_APPROVED:
                return '<h5><span class="badge badge-success">'.ctrans('texts.approved').'</span></h5>';
                break;
            case self::STATUS_EXPIRED:
                return '<h5><span class="badge badge-danger">'.ctrans('texts.expired').'</span></h5>';
                break;
            case self::STATUS_CONVERTED:
                return '<h5><span class="badge badge-light">'.ctrans('texts.converted').'</span></h5>';
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
                return ctrans('texts.pending');
                break;
            case self::STATUS_APPROVED:
                return ctrans('texts.approved');
                break;
            case self::STATUS_EXPIRED:
                return ctrans('texts.expired');
                break;
            case self::STATUS_CONVERTED:
                return ctrans('texts.converted');
                break;
            default:
                // code...
                break;
        }
    }

    /**
     * Check if the quote has been approved.
     *
     * @return bool
     */
    public function isApproved()
    {
        if ($this->status_id === $this::STATUS_APPROVED) {
            return true;
        }

        return false;
    }

    public function getValidUntilAttribute()
    {
        return $this->due_date;
    }

    public function getBalanceDueAttribute()
    {
        return $this->balance;
    }

    public function getTotalAttribute()
    {
        return $this->calc()->getTotal();
    }

    public function translate_entity()
    {
        return ctrans('texts.quote');
    }
    
    /**
     * calculateTemplate
     *
     * @param  string $entity_string
     * @return string
     */
    public function calculateTemplate(string $entity_string): string
    {
        return $entity_string;
    }
}
