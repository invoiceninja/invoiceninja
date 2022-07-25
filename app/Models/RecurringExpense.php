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

use App\Models\RecurringInvoice;
use App\Services\Recurring\RecurringService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class RecurringExpense extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'client_id',
        'assigned_user_id',
        'vendor_id',
        'invoice_id',
        'currency_id',
        'date',
        'invoice_currency_id',
        'amount',
        'foreign_amount',
        'exchange_rate',
        'private_notes',
        'public_notes',
        'bank_id',
        'transaction_id',
        'category_id',
        'tax_rate1',
        'tax_name1',
        'tax_rate2',
        'tax_name2',
        'tax_rate3',
        'tax_name3',
        'payment_date',
        'payment_type_id',
        'project_id',
        'transaction_reference',
        'invoice_documents',
        'should_be_invoiced',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'number',
        'tax_amount1',
        'tax_amount2',
        'tax_amount3',
        'uses_inclusive_taxes',
        'calculate_tax_by_amount',
        'frequency_id',
        'last_sent_date',
        'next_send_date',
        'remaining_cycles',
        'next_send_date_client',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Service entry points.
     */
    public function service() :RecurringService
    {
        return new RecurringService($this);
    }

    public function nextSendDate() :?Carbon
    {
        if (! $this->next_send_date) {
            return null;
        }

        switch ($this->frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addDay();
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeek();
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeeks(2);
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeeks(4);
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthNoOverflow();
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(2);
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(3);
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(4);
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(6);
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYear();
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYears(2);
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYears(3);
            default:
                return null;
        }
    }

    public function nextSendDateClient() :?Carbon
    {
        if (! $this->next_send_date) {
            return null;
        }

        switch ($this->frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addDay();
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeek();
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeeks(2);
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeeks(4);
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthNoOverflow();
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(2);
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(3);
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(4);
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(6);
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYear();
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYears(2);
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYears(3);
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

    public function recurringDates()
    {

        /* Return early if nothing to send back! */
        if ($this->status_id == RecurringInvoice::STATUS_COMPLETED ||
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

        if (! Carbon::parse($this->next_send_date)) {
            return $data;
        }

        $next_send_date = Carbon::parse($this->next_send_date)->copy();

        for ($x = 0; $x < $iterations; $x++) {
            // we don't add the days... we calc the day of the month!!
            $this->nextDateByFrequency($next_send_date);

            $data[] = [
                'send_date' => $next_send_date->format('Y-m-d'),
            ];
        }

        return $data;
    }

    public function nextDateByFrequency($date)
    {
        $offset = 0;

        if ($this->client) {
            $offset = $this->client->timezone_offset();
        }

        switch ($this->frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return $date->startOfDay()->addDay()->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return $date->startOfDay()->addWeek()->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return $date->startOfDay()->addWeeks(2)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return $date->startOfDay()->addWeeks(4)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return $date->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return $date->startOfDay()->addMonthsNoOverflow(2)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return $date->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return $date->startOfDay()->addMonthsNoOverflow(4)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return $date->addMonthsNoOverflow(6)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return $date->startOfDay()->addYear()->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return $date->startOfDay()->addYears(2)->addSeconds($offset);
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return $date->startOfDay()->addYears(3)->addSeconds($offset);
            default:
                return null;
        }
    }

    public function translate_entity()
    {
        return ctrans('texts.recurring_expense');
    }
}
