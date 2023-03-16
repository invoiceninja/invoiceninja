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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Task
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int|null $client_id
 * @property int|null $invoice_id
 * @property int|null $project_id
 * @property int|null $status_id
 * @property int|null $status_sort_order
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $duration
 * @property string|null $description
 * @property int $is_deleted
 * @property int $is_running
 * @property string|null $time_log
 * @property string|null $number
 * @property string $rate
 * @property int $invoice_documents
 * @property int $is_date_based
 * @property int|null $status_order
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\TaskStatus|null $status
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\TaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Task filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereInvoiceDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereIsDateBased($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereIsRunning($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatusOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatusSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereTimeLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Task withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @mixin \Eloquent
 */
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
