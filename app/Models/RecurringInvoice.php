<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use App\Models\Filterable;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class for Recurring Invoices.
 */
class RecurringInvoice extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    use MakesDates;
    /**
     * Invoice Statuses.
     */
    const STATUS_DRAFT = 2;
    const STATUS_ACTIVE = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_PENDING = -1;
    const STATUS_COMPLETED = -2;

    /**
     * Recurring intervals //todo MAP WHEN WE MIGRATE.
     */

    /* Make sure we support overflow!!!!!!!!!!
    $start = Carbon::today();
    $subscription = Carbon::parse('2017-12-31');

    foreach (range(1, 12) as $month) {
        $day = $start->addMonthNoOverflow()->thisDayOrLast($subscription->day);

        echo "You will be billed on {$day} in month {$month}\n";
    }
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
        'number',
        'discount',
        'is_amount_discount',
        'po_number',
        'date',
        'due_date',
        'line_items',
        'settings',
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
        'start_date',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
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
        return $this->hasMany(Invoice::class, 'id', 'recurring_id')->withTrashed();
    }

    public function invitations()
    {
        $this->morphMany(RecurringInvoiceInvitation::class);
    }

    public function getStatusAttribute()
    {
        if ($this->status_id == self::STATUS_ACTIVE && $this->start_date > Carbon::now()) { //marked as active, but yet to fire first cycle
            return self::STATUS_PENDING;
        } elseif ($this->status_id == self::STATUS_ACTIVE && $this->next_send_date > Carbon::now()) {
            return self::STATUS_COMPLETED;
        } else {
            return $this->status_id;
        }
    }

    public function nextSendDate() :?Carbon
    {
        switch ($this->frequency_id) {
            case self::FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date->addWeek());
            case self::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date->addWeeks(2));
            case self::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date->addWeeks(4));
            case self::FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date->addMonthNoOverflow());
            case self::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date->addMonthsNoOverflow(2));
            case self::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date->addMonthsNoOverflow(3));
            case self::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date->addMonthsNoOverflow(4));
            case self::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date->addMonthsNoOverflow(6));
            case self::FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date->addYear());
            case self::FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date->addYears(2));
            case self::FREQUENCY_THREE_YEARS:
                return Carbon::parse($this->next_send_date->addYears(3));
            default:
                return null;
        }
    }

    public function remainingCycles() : int
    {
        if ($this->remaining_cycles == 0) {
            return 0;
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
                return '<h4><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h4>';
                break;
            case self::STATUS_ACTIVE:
                return '<h4><span class="badge badge-primary">'.ctrans('texts.partial').'</span></h4>';
                break;
            case self::STATUS_COMPLETED:
                return '<h4><span class="badge badge-success">'.ctrans('texts.status_completed').'</span></h4>';
                break;
            case self::STATUS_CANCELLED:
                return '<h4><span class="badge badge-danger">'.ctrans('texts.overdue').'</span></h4>';
                break;
            default:
                // code...
                break;
        }
    }

    public static function frequencyForKey(int $frequency_id) :string
    {
        switch ($frequency_id) {
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
            default:
                // code...
                break;
        }
    }

    public function recurringDates()
    {
        //todo send back a list of the next send dates and due dates
    }
}
