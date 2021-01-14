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
        'assigned_user_id',
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'project_id',
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
        'design_id',
        'exchange_rate',
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

    protected $dates = [];

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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
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

    public function pdf_file_path($invitation = null)
    {
        if (! $invitation) {
            $invitation = $this->invitations->where('client_contact_id', $this->client->primary_contact()->first()->id)->first();
        }

        $storage_path = Storage::url($this->client->quote_filepath().$this->number.'.pdf');

        if (Storage::exists($this->client->quote_filepath().$this->number.'.pdf')) {
            return $storage_path;
        }

        event(new QuoteWasUpdated($this, $this->company, Ninja::eventVars()));

        CreateEntityPdf::dispatchNow($invitation);

        return $storage_path;
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
}
