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

use App\Services\Recurring\RecurringService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\RecurringExpense
 *
 * @property int $id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $company_id
 * @property int|null $vendor_id
 * @property int $user_id
 * @property int $status_id
 * @property int|null $invoice_id
 * @property int|null $client_id
 * @property int|null $bank_id
 * @property int|null $project_id
 * @property int|null $payment_type_id
 * @property int|null $recurring_expense_id
 * @property bool $is_deleted
 * @property int $uses_inclusive_taxes
 * @property string|null $tax_name1
 * @property string|null $tax_name2
 * @property string|null $tax_name3
 * @property string|null $date
 * @property string|null $payment_date
 * @property int $should_be_invoiced
 * @property int $invoice_documents
 * @property string|null $transaction_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $category_id
 * @property int $calculate_tax_by_amount
 * @property string|null $tax_amount1
 * @property string|null $tax_amount2
 * @property string|null $tax_amount3
 * @property string|null $tax_rate1
 * @property string|null $tax_rate2
 * @property string|null $tax_rate3
 * @property string|null $amount
 * @property string|null $foreign_amount
 * @property string $exchange_rate
 * @property int|null $assigned_user_id
 * @property string|null $number
 * @property int|null $invoice_currency_id
 * @property int|null $currency_id
 * @property string|null $private_notes
 * @property string|null $public_notes
 * @property string|null $transaction_reference
 * @property int $frequency_id
 * @property string|null $last_sent_date
 * @property string|null $next_send_date
 * @property int|null $remaining_cycles
 * @property string|null $next_send_date_client
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @property-read \App\Models\ExpenseCategory|null $category
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\RecurringExpenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCalculateTaxByAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereForeignAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereFrequencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereInvoiceCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereInvoiceDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereLastSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereNextSendDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereNextSendDateClient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense wherePaymentTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense wherePublicNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereRecurringExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereRemainingCycles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereShouldBeInvoiced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxAmount1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxAmount2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxAmount3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxName1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxName2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxName3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxRate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxRate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTaxRate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereUsesInclusiveTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringExpense withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @mixin \Eloquent
 */
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

    public function company():\Illuminate\Database\Eloquent\Relations\BelongsTo
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

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class)->withTrashed();
    }


    /**
     * Service entry points.
     */
    public function service(): RecurringService
    {
        return new RecurringService($this);
    }

    public function nextSendDate(): ?Carbon
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

    public function nextSendDateClient(): ?Carbon
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

    public function remainingCycles(): int
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
