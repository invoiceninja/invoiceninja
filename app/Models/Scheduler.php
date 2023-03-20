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

use App\Models\Company;
use App\Models\BaseModel;
use App\Models\RecurringInvoice;
use App\Services\Scheduler\SchedulerService;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Scheduler
 *
 * @property bool paused
 * @property bool is_deleted
 * @property \Carbon\Carbon|mixed start_from
 * @property int frequency_id
 * @property \Carbon\Carbon|mixed next_run
 * @property int company_id
 * @property int updated_at
 * @property int created_at
 * @property int deleted_at
 * @property string action_name
 * @property mixed company
 * @property array parameters
 * @property string action_class
 * @property int $id
 * @property bool $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property array|null $parameters
 * @property int $company_id
 * @property bool $is_paused
 * @property int|null $frequency_id
 * @property \Illuminate\Support\Carbon|null $next_run
 * @property \Illuminate\Support\Carbon|null $next_run_client
 * @property int $user_id
 * @property string $name
 * @property string $template
 * @property int|null $remaining_cycles
 * @property-read \App\Models\Company $company
 * @property-read mixed $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\SchedulerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereFrequencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereIsPaused($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereNextRun($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereNextRunClient($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereParameters($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereRemainingCycles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereTemplate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Scheduler withoutTrashed()
 * @mixin \Eloquent
 */
class Scheduler extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    
    protected $fillable = [
        'name',
        'frequency_id',
        'next_run',
        'next_run_client',
        'template',
        'is_paused',
        'parameters',
        'remaining_cycles',
    ];

    protected $casts = [
        'next_run' => 'datetime',
        'next_run_client' => 'datetime',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_paused' => 'boolean',
        'is_deleted' => 'boolean',
        'parameters' => 'array',
    ];

    protected $appends = [
        'hashed_id',
    ];

    /**
     * Service entry points.
     */
    public function service(): SchedulerService
    {
        return new SchedulerService($this);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    /**
     * remainingCycles
     *
     * @return int
     */
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
    
    public function calculateNextRun()
    {
        if (! $this->next_run) {
            return null;
        }

        $offset = $this->company->timezone_offset();

        switch ($this->frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                $next_run = now()->startOfDay()->addDay();
                break;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $next_run = now()->startOfDay()->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $next_run = now()->startOfDay()->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $next_run = now()->startOfDay()->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $next_run = now()->startOfDay()->addMonthNoOverflow();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $next_run = now()->startOfDay()->addMonthsNoOverflow(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $next_run = now()->startOfDay()->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $next_run = now()->startOfDay()->addYears(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                $next_run = now()->startOfDay()->addYears(3);
                break;
            default:
                $next_run =  null;
        }


        $this->next_run_client = $next_run ?: null;
        $this->next_run = $next_run ? $next_run->copy()->addSeconds($offset) : null;
        $this->save();
    }


}
