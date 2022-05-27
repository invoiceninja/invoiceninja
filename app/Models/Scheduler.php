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

use App\Services\TaskScheduler\TaskSchedulerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property boolean paused
 * @property boolean is_deleted
 * @property \Carbon\Carbon|mixed start_from
 * @property string repeat_every
 * @property \Carbon\Carbon|mixed scheduled_run
 * @property mixed job
 * @property integer company_id
 * @property integer updated_at
 * @property integer created_at
 * @property integer deleted_at
 */
class Scheduler extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'start_from',
        'paused',
        'repeat_every',
        'scheduled_run',
        'company_id'
    ];
    protected $casts = [
        'start_from' => 'timestamp',
        'scheduled_run' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'paused' => 'boolean',
        'is_deleted' => 'boolean',
    ];
    protected $appends = ['linked_job'];

    const DAILY = 'DAY';
    const WEEKLY = 'WEEK';
    const BIWEEKLY = 'BIWEEKLY';
    const MONTHLY = 'MONTH';
    const QUARTERLY = '3MONTHS';
    const ANNUALLY = 'YEAR';

    public function getLinkedJobAttribute()
    {
        return $this->job ?? [];
    }

    /**
     * Service entry points.
     */
    public function service(): TaskSchedulerService
    {
        return new TaskSchedulerService($this);
    }

    public function job(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ScheduledJob::class, 'scheduler_id', 'id');
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }


    public function nextScheduledDate(): ?Carbon
    {

        $offset = 0;

        $entity_send_time = $this->company->settings->entity_send_time;

        if ($entity_send_time != 0) {
            $timezone = $this->company->timezone();

            $offset -= $timezone->utc_offset;
            $offset += ($entity_send_time * 3600);
        }

        /*
        As we are firing at UTC+0 if our offset is negative it is technically firing the day before so we always need
        to add ON a day - a day = 86400 seconds
        */

        if ($offset < 0)
            $offset += 86400;

        switch ($this->repeat_every) {
            case self::DAILY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addDay()->addSeconds($offset);
            case self::WEEKLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addWeek()->addSeconds($offset);
            case self::BIWEEKLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addWeeks(2)->addSeconds($offset);
            case self::MONTHLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case self::QUARTERLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case self::ANNUALLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addYearNoOverflow()->addSeconds($offset);
            default:
                return null;
        }
    }
}
