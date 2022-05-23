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

use App\Services\TaskScheduler\TaskSchedulerService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property boolean paused
 * @property boolean archived
 * @property \Carbon\Carbon|mixed start_from
 * @property string repeat_every
 * @property \Carbon\Carbon|mixed scheduled_run
 * @property mixed job
 * @property integer company_id
 */
class Scheduler extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_from',
        'paused',
        'archived',
        'repeat_every',
        'scheduled_run',
        'company_id'
    ];
    protected $appends = ['linked_job'];

    const DAILY = 'DAY';
    const WEEKLY = 'WEEK';
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

    public function nextScheduledDate() :?Carbon
    {

        /*
        As we are firing at UTC+0 if our offset is negative it is technically firing the day before so we always need
        to add ON a day - a day = 86400 seconds
        */
            $offset = 86400;

        switch ($this->repeat_every) {
            case self::DAILY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addDay()->addSeconds($offset);
            case self::WEEKLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addWeek()->addSeconds($offset);
            case self::MONTHLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addMonth()->addSeconds($offset);
            case self::QUARTERLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addMonths(3)->addSeconds($offset);
            case self::ANNUALLY:
                return Carbon::parse($this->scheduled_run)->startOfDay()->addYear()->addSeconds($offset);
            default:
                return null;
        }
    }
}
