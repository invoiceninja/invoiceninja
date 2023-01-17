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

use App\Services\Scheduler\SchedulerService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
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
 */
class Scheduler extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'frequency_id',
        'next_run',
        'next_run_client',
        'template',
        'is_paused',
        'parameters',
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

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // public function nextScheduledDate(): ?Carbon
    // {
    //     $offset = 0;

    //     $entity_send_time = $this->company->settings->entity_send_time;

    //     if ($entity_send_time != 0) {
    //         $timezone = $this->company->timezone();

    //         $offset -= $timezone->utc_offset;
    //         $offset += ($entity_send_time * 3600);
    //     }

    //     /*
    //     As we are firing at UTC+0 if our offset is negative it is technically firing the day before so we always need
    //     to add ON a day - a day = 86400 seconds
    //     */

    //     if ($offset < 0) {
    //         $offset += 86400;
    //     }

    //     switch ($this->repeat_every) {
    //         case self::DAILY:
    //             return Carbon::parse($this->scheduled_run)->startOfDay()->addDay()->addSeconds($offset);
    //         case self::WEEKLY:
    //             return Carbon::parse($this->scheduled_run)->startOfDay()->addWeek()->addSeconds($offset);
    //         case self::BIWEEKLY:
    //             return Carbon::parse($this->scheduled_run)->startOfDay()->addWeeks(2)->addSeconds($offset);
    //         case self::MONTHLY:
    //             return Carbon::parse($this->scheduled_run)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
    //         case self::QUARTERLY:
    //             return Carbon::parse($this->scheduled_run)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
    //         case self::ANNUALLY:
    //             return Carbon::parse($this->scheduled_run)->startOfDay()->addYearNoOverflow()->addSeconds($offset);
    //         default:
    //             return null;
    //     }
    // }
}
