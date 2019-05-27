<?php

namespace App\Models;

use App\Events\TaskWasCreated;
use App\Events\TaskWasUpdated;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;
use Utils;

/**
 * Class Task.
 */
class Task extends EntityModel
{
    use SoftDeletes;
    use PresentableTrait;

    /**
     * @var array
     */
    protected $fillable = [
        'client_id',
        'description',
        'time_log',
        'is_running',
        'custom_value1',
        'custom_value2',
    ];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_TASK;
    }

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\TaskPresenter';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function project()
    {
        return $this->belongsTo('App\Models\Project')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function task_status()
    {
        return $this->belongsTo('App\Models\TaskStatus')->withTrashed();
    }

    /**
     * @param $task
     *
     * @return string
     */
    public static function calcStartTime($task)
    {
        $parts = json_decode($task->time_log) ?: [];

        if (count($parts)) {
            return Utils::timestampToDateTimeString($parts[0][0]);
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    public function getStartTime()
    {
        return self::calcStartTime($this);
    }

    public function getLastStartTime()
    {
        $parts = json_decode($this->time_log) ?: [];

        if (count($parts)) {
            $index = count($parts) - 1;

            return $parts[$index][0];
        } else {
            return '';
        }
    }

    /**
     * @param $task
     *
     * @return int
     */
    public static function calcDuration($task, $startTimeCutoff = 0, $endTimeCutoff = 0)
    {
        $duration = 0;
        $parts = json_decode($task->time_log) ?: [];

        foreach ($parts as $part) {
            $startTime = $part[0];
            if (count($part) == 1 || ! $part[1]) {
                $endTime = time();
            } else {
                $endTime = $part[1];
            }

            if ($startTimeCutoff) {
                $startTime = max($startTime, $startTimeCutoff);
            }
            if ($endTimeCutoff) {
                $endTime = min($endTime, $endTimeCutoff);
            }

            $duration += max($endTime - $startTime, 0);
        }

        return round($duration);
    }

    /**
     * @return int
     */
    public function getDuration($startTimeCutoff = 0, $endTimeCutoff = 0)
    {
        return self::calcDuration($this, $startTimeCutoff, $endTimeCutoff);
    }

    /**
     * @return float
     */
    public function getRate()
    {
        $value = 0;

        if ($this->project && floatval($this->project->task_rate)) {
            $value = $this->project->task_rate;
        } elseif ($this->client && floatval($this->client->task_rate)) {
            $value = $this->client->task_rate;
        } else {
            $value = $this->account->task_rate;
        }

        return Utils::roundSignificant($value);
    }

    /**
     * @return int
     */
    public function getCurrentDuration()
    {
        $parts = json_decode($this->time_log) ?: [];
        $part = $parts[count($parts) - 1];

        if (count($part) == 1 || ! $part[1]) {
            return time() - $part[0];
        } else {
            return 0;
        }
    }

    /**
     * @return bool
     */
    public function hasPreviousDuration()
    {
        $parts = json_decode($this->time_log) ?: [];

        return count($parts) && (count($parts[0]) && $parts[0][1]);
    }

    /**
     * @return float
     */
    public function getHours()
    {
        return round($this->getDuration() / (60 * 60), 2);
    }

    /**
     * Gets the route to the tasks edit action.
     *
     * @return string
     */
    public function getRoute()
    {
        return "/tasks/{$this->public_id}/edit";
    }

    public function getName()
    {
        return '#' . $this->public_id;
    }

    public function getDisplayName()
    {
        if ($this->description) {
            return Utils::truncateString($this->description, 16);
        }

        return '#' . $this->public_id;
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        $query->whereRaw('cast(substring(time_log, 3, 10) as unsigned) <= ' . $endDate->modify('+1 day')->format('U'))
            ->whereRaw('case
                when is_running then unix_timestamp()
                else cast(substring(time_log, length(time_log) - 11, 10) as unsigned)
            end >= ' . $startDate->format('U'));

        return $query;
    }

    public static function getStatuses($entityType = false)
    {
        $statuses = [];

        $taskStatues = TaskStatus::scope()->orderBy('sort_order')->get();

        foreach ($taskStatues as $status) {
            $statuses[$status->public_id] = $status->name;
        }

        $statuses[TASK_STATUS_LOGGED] = trans('texts.logged');
        $statuses[TASK_STATUS_RUNNING] = trans('texts.running');
        $statuses[TASK_STATUS_INVOICED] = trans('texts.invoiced');
        $statuses[TASK_STATUS_PAID] = trans('texts.paid');

        return $statuses;
    }

    public static function calcStatusLabel($isRunning, $balance, $invoiceNumber, $taskStatus)
    {
        if ($invoiceNumber) {
            if (floatval($balance) > 0) {
                $label = trans('texts.invoiced');
            } else {
                $label = trans('texts.paid');
            }
        } elseif ($taskStatus) {
            $label = $taskStatus;
        } else {
            $label = trans('texts.logged');
        }

        if ($isRunning) {
            $label .= ' | ' . trans('texts.running');
        }

        return $label;
    }

    public static function calcStatusClass($isRunning, $balance, $invoiceNumber)
    {
        if ($invoiceNumber) {
            if (floatval($balance)) {
                return 'default';
            } else {
                return 'success';
            }
        } elseif ($isRunning) {
            return 'primary';
        } else {
            return 'info';
        }
    }

    public function statusClass()
    {
        if ($this->invoice) {
            $balance = $this->invoice->balance;
            $invoiceNumber = $this->invoice->invoice_number;
        } else {
            $balance = 0;
            $invoiceNumber = false;
        }

        return static::calcStatusClass($this->is_running, $balance, $invoiceNumber);
    }

    public function statusLabel()
    {
        if ($this->invoice) {
            $balance = $this->invoice->balance;
            $invoiceNumber = $this->invoice->invoice_number;
        } else {
            $balance = 0;
            $invoiceNumber = false;
        }

        $taskStatus = $this->task_status ? $this->task_status->name : false;

        return static::calcStatusLabel($this->is_running, $balance, $invoiceNumber, $taskStatus);
    }
}

Task::created(function ($task) {
    event(new TaskWasCreated($task));
});

Task::updated(function ($task) {
    event(new TaskWasUpdated($task));
});
