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

use App\Services\Scheduler\SchedulerService;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
