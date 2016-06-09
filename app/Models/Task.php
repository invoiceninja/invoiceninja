<?php namespace App\Models;

use DB;
use Utils;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Task extends EntityModel
{
    use SoftDeletes;
    use PresentableTrait;

    protected $presenter = 'App\Ninja\Presenters\TaskPresenter';

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public static function calcStartTime($task)
    {
        $parts = json_decode($task->time_log) ?: [];

        if (count($parts)) {
            return Utils::timestampToDateTimeString($parts[0][0]);
        } else {
            return '';
        }
    }

    public function getStartTime()
    {
        return self::calcStartTime($this);
    }

    public static function calcDuration($task)
    {
        $duration = 0;
        $parts = json_decode($task->time_log) ?: [];

        foreach ($parts as $part) {
            if (count($part) == 1 || !$part[1]) {
                $duration += time() - $part[0];
            } else {
                $duration += $part[1] - $part[0];
            }
        }

        return $duration;
    }

    public function getDuration()
    {
        return self::calcDuration($this);
    }

    public function getCurrentDuration()
    {
        $parts = json_decode($this->time_log) ?: [];
        $part = $parts[count($parts)-1];

        if (count($part) == 1 || !$part[1]) {
            return time() - $part[0];
        } else {
            return 0;
        }
    }

    public function hasPreviousDuration()
    {
        $parts = json_decode($this->time_log) ?: [];
        return count($parts) && (count($parts[0]) && $parts[0][1]);
    }

    public function getHours()
    {
        return round($this->getDuration() / (60 * 60), 2);
    }
}
