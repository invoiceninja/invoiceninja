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

use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Presenters\RecurringInvoicePresenter;
use App\Services\Recurring\RecurringService;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Recurring\HasRecurrence;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class for Recurring Invoices.
 */
class RecurringInvoice extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    use MakesDates;
    use HasRecurrence;
    use PresentableTrait;

    protected $presenter = RecurringInvoicePresenter::class;

    /**
     * Invoice Statuses.
     */
    const STATUS_DRAFT = 1;

    const STATUS_ACTIVE = 2;

    const STATUS_PAUSED = 3;

    const STATUS_COMPLETED = 4;

    const STATUS_PENDING = -1;

    /**
     * Invoice Frequencies.
     */
    const FREQUENCY_DAILY = 1;

    const FREQUENCY_WEEKLY = 2;

    const FREQUENCY_TWO_WEEKS = 3;

    const FREQUENCY_FOUR_WEEKS = 4;

    const FREQUENCY_MONTHLY = 5;

    const FREQUENCY_TWO_MONTHS = 6;

    const FREQUENCY_THREE_MONTHS = 7;

    const FREQUENCY_FOUR_MONTHS = 8;

    const FREQUENCY_SIX_MONTHS = 9;

    const FREQUENCY_ANNUALLY = 10;

    const FREQUENCY_TWO_YEARS = 11;

    const FREQUENCY_THREE_YEARS = 12;

    const RECURS_INDEFINITELY = -1;

    protected $fillable = [
        'client_id',
        'project_id',
        'number',
        'discount',
        'is_amount_discount',
        'po_number',
        'date',
        'due_date',
        'due_date_days',
        'line_items',
        'footer',
        'public_notes',
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
        'frequency_id',
        'next_send_date',
        'remaining_cycles',
        'auto_bill',
        'auto_bill_enabled',
        'design_id',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
        'custom_surcharge_tax1',
        'custom_surcharge_tax2',
        'custom_surcharge_tax3',
        'custom_surcharge_tax4',
        'design_id',
        'assigned_user_id',
        'exchange_rate',
        'vendor_id',
        'next_send_date_client',
    ];

    protected $casts = [
        'settings' => 'object',
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $appends = [
        'hashed_id',
        'status',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function getDateAttribute($value)
    {
        if (! empty($value)) {
            return (new Carbon($value))->format('Y-m-d');
        }

        return $value;
    }

    public function getDueDateAttribute($value)
    {
        if (! empty($value)) {
            return (new Carbon($value))->format('Y-m-d');
        }

        return $value;
    }

    public function getPartialDueDateAttribute($value)
    {
        if (! empty($value)) {
            return (new Carbon($value))->format('Y-m-d');
        }

        return $value;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'recurring_id', 'id')->withTrashed();
    }

    public function invitations()
    {
        return $this->hasMany(RecurringInvoiceInvitation::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getStatusAttribute()
    {
        if ($this->status_id == self::STATUS_ACTIVE && Carbon::parse($this->next_send_date)->isFuture()) {
            return self::STATUS_PENDING;
        } else {
            return $this->status_id;
        }
    }

    public function nextSendDate() :?Carbon
    {
        if (! $this->next_send_date_client) {
            return null;
        }

        $offset = $this->client->timezone_offset();

        /* If this setting is enabled, the recurring invoice may be set in the past */

        if ($this->company->stop_on_unpaid_recurring) {

                /* Lets set the next send date to now so we increment from today, rather than in the past*/
            if (Carbon::parse($this->next_send_date)->lt(now()->subDays(3))) {
                $this->next_send_date_client = now()->format('Y-m-d');
            }
        }

        /*
        As we are firing at UTC+0 if our offset is negative it is technically firing the day before so we always need
        to add ON a day - a day = 86400 seconds
        */
        // if($offset < 0)
        //     $offset += 86400;

        switch ($this->frequency_id) {
            case self::FREQUENCY_DAILY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addDay()->addSeconds($offset);
            case self::FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addWeek()->addSeconds($offset);
            case self::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addWeeks(2)->addSeconds($offset);
            case self::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addWeeks(4)->addSeconds($offset);
            case self::FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case self::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case self::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(4)->addSeconds($offset);
            case self::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(6)->addSeconds($offset);
            case self::FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addYear()->addSeconds($offset);
            case self::FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addYears(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_YEARS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addYears(3)->addSeconds($offset);
            default:
                return null;
        }
    }

    public function nextSendDateClient() :?Carbon
    {
        if (! $this->next_send_date_client) {
            return null;
        }

        /* If this setting is enabled, the recurring invoice may be set in the past */

        if ($this->company->stop_on_unpaid_recurring) {

                /* Lets set the next send date to now so we increment from today, rather than in the past*/
            if (Carbon::parse($this->next_send_date)->lt(now()->subDays(3))) {
                $this->next_send_date_client = now()->format('Y-m-d');
            }
        }

        switch ($this->frequency_id) {
            case self::FREQUENCY_DAILY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addDay();
            case self::FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addWeek();
            case self::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addWeeks(2);
            case self::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addWeeks(4);
            case self::FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthNoOverflow();
            case self::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(2);
            case self::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(3);
            case self::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(4);
            case self::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addMonthsNoOverflow(6);
            case self::FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addYear();
            case self::FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addYears(2);
            case self::FREQUENCY_THREE_YEARS:
                return Carbon::parse($this->next_send_date_client)->startOfDay()->addYears(3);
            default:
                return null;
        }
    }

    public function nextDateByFrequency($date)
    {
        $offset = $this->client->timezone_offset();

        switch ($this->frequency_id) {
            case self::FREQUENCY_DAILY:
                return Carbon::parse($date)->startOfDay()->addDay()->addSeconds($offset);
            case self::FREQUENCY_WEEKLY:
                return Carbon::parse($date)->startOfDay()->addWeek()->addSeconds($offset);
            case self::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($date)->startOfDay()->addWeeks(2)->addSeconds($offset);
            case self::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($date)->startOfDay()->addWeeks(4)->addSeconds($offset);
            case self::FREQUENCY_MONTHLY:
                return Carbon::parse($date)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case self::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($date)->startOfDay()->addMonthsNoOverflow(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($date)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case self::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($date)->startOfDay()->addMonthsNoOverflow(4)->addSeconds($offset);
            case self::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($date)->addMonthsNoOverflow(6)->addSeconds($offset);
            case self::FREQUENCY_ANNUALLY:
                return Carbon::parse($date)->startOfDay()->addYear()->addSeconds($offset);
            case self::FREQUENCY_TWO_YEARS:
                return Carbon::parse($date)->startOfDay()->addYears(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_YEARS:
                return Carbon::parse($date)->startOfDay()->addYears(3)->addSeconds($offset);
            default:
                return null;
        }
    }

    public function remainingCycles() : int
    {
        if ($this->remaining_cycles == 0) {
            return 0;
        } elseif ($this->remaining_cycles == -1) {
            return -1;
        } else {
            return $this->remaining_cycles - 1;
        }
    }

    public function setCompleted() :  void
    {
        $this->status_id = self::STATUS_COMPLETED;
        $this->next_send_date = null;
        $this->remaining_cycles = 0;
        $this->save();
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h4><span class="badge badge-light">'.ctrans('texts.draft').'</span></h4>';
                break;
            case self::STATUS_PENDING:
                return '<h4><span class="badge badge-primary">'.ctrans('texts.pending').'</span></h4>';
                break;
            case self::STATUS_ACTIVE:
                return '<h4><span class="badge badge-primary">'.ctrans('texts.active').'</span></h4>';
                break;
            case self::STATUS_COMPLETED:
                return '<h4><span class="badge badge-success">'.ctrans('texts.status_completed').'</span></h4>';
                break;
            case self::STATUS_PAUSED:
                return '<h4><span class="badge badge-danger">'.ctrans('texts.paused').'</span></h4>';
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
            case self::STATUS_PENDING:
                return ctrans('texts.pending');
                break;
            case self::STATUS_ACTIVE:
                return ctrans('texts.active');
                break;
            case self::STATUS_COMPLETED:
                return ctrans('texts.status_completed');
                break;
            case self::STATUS_PAUSED:
                return ctrans('texts.paused');
                break;
            default:
                // code...
                break;
        }
    }

    public static function frequencyForKey(int $frequency_id) :string
    {
        switch ($frequency_id) {
            case self::FREQUENCY_DAILY:
                return ctrans('texts.freq_daily');
                break;
            case self::FREQUENCY_WEEKLY:
                return ctrans('texts.freq_weekly');
                break;
            case self::FREQUENCY_TWO_WEEKS:
                return ctrans('texts.freq_two_weeks');
                break;
            case self::FREQUENCY_FOUR_WEEKS:
                return ctrans('texts.freq_four_weeks');
                break;
            case self::FREQUENCY_MONTHLY:
                return ctrans('texts.freq_monthly');
                break;
            case self::FREQUENCY_TWO_MONTHS:
                return ctrans('texts.freq_two_months');
                break;
            case self::FREQUENCY_THREE_MONTHS:
                return ctrans('texts.freq_three_months');
                break;
            case self::FREQUENCY_FOUR_MONTHS:
                return ctrans('texts.freq_four_months');
                break;
            case self::FREQUENCY_SIX_MONTHS:
                return ctrans('texts.freq_six_months');
                break;
            case self::FREQUENCY_ANNUALLY:
                return ctrans('texts.freq_annually');
                break;
            case self::FREQUENCY_TWO_YEARS:
                return ctrans('texts.freq_two_years');
                break;
            case self::FREQUENCY_THREE_YEARS:
                return ctrans('texts.freq_three_years');
                break;
            default:
                return '';
                break;
        }
    }

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

    /*
     * Important to note when playing with carbon dates - in order
     * not to modify the original instance, always use a `->copy()`
     *
     */
    public function recurringDates()
    {

        /* Return early if nothing to send back! */
        if ($this->status_id == self::STATUS_COMPLETED ||
            $this->remaining_cycles == 0 ||
            ! $this->next_send_date) {
            return [];
        }

        /* Endless - lets send 10 back*/
        $iterations = $this->remaining_cycles;

        if ($this->remaining_cycles == -1) {
            $iterations = 10;
        }

        $data = [];

        if (! Carbon::parse($this->next_send_date_client)) {
            return $data;
        }

        $next_send_date = Carbon::parse($this->next_send_date_client)->copy();

        for ($x = 0; $x < $iterations; $x++) {
            // we don't add the days... we calc the day of the month!!
            $next_due_date = $this->calculateDueDate($next_send_date->copy()->format('Y-m-d'));
            $next_due_date_string = $next_due_date ? $next_due_date->format('Y-m-d') : '';

            $next_send_date = Carbon::parse($next_send_date);

            $data[] = [
                'send_date' => $next_send_date->format('Y-m-d'),
                'due_date' => $next_due_date_string,
            ];

            /* Fixes the timeshift in case the offset is negative which cause a infinite loop due to UTC +0*/
            if ($this->client->timezone_offset() < 0) {
                $next_send_date = $this->nextDateByFrequency($next_send_date->addDay()->format('Y-m-d'));
            } else {
                $next_send_date = $this->nextDateByFrequency($next_send_date->format('Y-m-d'));
            }
        }

        return $data;
    }

    public function calculateDueDate($date)
    {
        switch ($this->due_date_days) {
            case 'terms':
            case '':
                return $this->calculateDateFromTerms($date);
                break;

            default:
                return $this->setDayOfMonth($date, $this->due_date_days);
                break;
        }
    }

    /**
     * Calculates a date based on the client payment terms.
     *
     * @param  Carbon $date A given date
     * @return null|Carbon  The date
     */
    public function calculateDateFromTerms($date)
    {
        $new_date = Carbon::parse($date);

        $client_payment_terms = $this->client->getSetting('payment_terms');

        if ($client_payment_terms == '') {//no due date! return null;
            return null;
        }

        return $new_date->addDays($client_payment_terms); //add the number of days in the payment terms to the date
    }

    /**
     * Service entry points.
     */
    public function service() :RecurringService
    {
        return new RecurringService($this);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function translate_entity()
    {
        return ctrans('texts.recurring_invoice');
    }
}
