<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Events\Quote\QuoteWasUpdated;
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
                $invitation->save();
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
}
