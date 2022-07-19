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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Task extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    
    protected $fillable = [
        'client_id',
        'invoice_id',
        'project_id',
        'assigned_user_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'description',
        'is_running',
        'time_log',
        'status_id',
        'status_sort_order', //deprecated
        'invoice_documents',
        'rate',
        'number',
        'is_date_based',
        'status_order',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function status()
    {
        return $this->belongsTo(TaskStatus::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }










    public function calcStartTime()
    {
        $parts = json_decode($this->time_log) ?: [];

        if (count($parts)) {
            return Carbon::createFromTimeStamp($parts[0][0])->timestamp;
        } else {
            return null;
        }
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

    public function calcDuration($start_time_cutoff = 0, $end_time_cutoff = 0)
    {
        $duration = 0;
        $parts = json_decode($this->time_log) ?: [];

        foreach ($parts as $part) {
            $start_time = $part[0];
            if (count($part) == 1 || ! $part[1]) {
                $end_time = time();
            } else {
                $end_time = $part[1];
            }

            if ($start_time_cutoff) {
                $start_time = max($start_time, $start_time_cutoff);
            }
            if ($end_time_cutoff) {
                $end_time = min($end_time, $end_time_cutoff);
            }

            $duration += max($end_time - $start_time, 0);
        }

        return round($duration);
    }

    public function translate_entity()
    {
        return ctrans('texts.task');
    }
}