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

/**
 * @property boolean paused
 * @property boolean archived
 * @property \Carbon\Carbon|mixed start_from
 * @property string repeat_every
 * @property \Carbon\Carbon|mixed scheduled_run
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
}
