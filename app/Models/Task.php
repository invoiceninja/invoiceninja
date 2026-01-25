<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Carbon\CarbonInterval;
use App\Models\CompanyUser;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;
use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Libraries\Currency\Conversion\CurrencyApi;

/**
 * App\Models\Task
 *
 * @property int $id
 * @property string|null $hash
 * @property object|null $meta
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
 * @property bool $is_deleted
 * @property bool $is_running
 * @property string|null $time_log
 * @property string|null $number
 * @property float $rate
 * @property string $calculated_start_date
 * @property bool $invoice_documents
 * @property int $is_date_based
 * @property int|null $status_order
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\TaskStatus|null $status
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\TaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Task filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
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
    use Searchable;


    public static array $bulk_update_columns = [
        'status_id',
        'client_id',
        'project_id',
        'assigned_user_id',
    ];

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
        'hash',
        'meta',
    ];

    protected $casts = [
        'meta' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $with = [];

    protected $touches = ['project'];

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return 'tasks';
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function toSearchableArray()
    {
        $locale = $this->company->locale();

        App::setLocale($locale);

        $project = $this->project ? " | [ {$this->project->name} ]" : ' ';
        $client = $this->client ? " | {$this->client->present()->name()} ]" : ' ';

        // Get basic data
        $data = [
            'id' => $this->company->db.":".$this->id,
            'name' => ctrans('texts.task') . " " . ($this->number ?? '') . $project . $client,
            'hashed_id' => $this->hashed_id,
            'number' => (string)$this->number,
            'description' => (string)$this->description,
            'task_rate' => (float) $this->rate,
            'is_deleted' => (bool) $this->is_deleted,
            'custom_value1' => (string) $this->custom_value1,
            'custom_value2' => (string) $this->custom_value2,
            'custom_value3' => (string) $this->custom_value3,
            'custom_value4' => (string) $this->custom_value4,
            'company_key' => $this->company->company_key,
            'time_log' => $this->normalizeTimeLog($this->time_log),
            'calculated_start_date' => (string) $this->calculated_start_date,
        ];

        return $data;
    }

    /**
     * Normalize time_log for Elasticsearch indexing
     * Handles polymorphic structure: [start, end?, description?, billable?]
     */
    private function normalizeTimeLog($time_log): array
    {
        // Handle null/empty cases
        if (empty($time_log)) {
            return [];
        }

        $logs = json_decode($time_log, true);

        // Validate decoded data
        if (!is_array($logs) || empty($logs)) {
            return [];
        }

        $normalized = [];

        foreach ($logs as $log) {
            // Skip invalid entries
            if (!is_array($log) || !isset($log[0])) {
                continue;
            }

            $normalized[] = [
                'start_time' => (int) $log[0],
                'end_time' => isset($log[1]) && $log[1] !== 0 ? (int) $log[1] : 0,
                'description' => isset($log[2]) ? trim((string) $log[2]) : '',
                'billable' => isset($log[3]) ? (bool) $log[3] : false,
                'is_running' => isset($log[1]) && $log[1] === 0,
            ];
        }

        return $normalized;
    }

    public function getScoutKey()
    {
        return $this->company->db.":".$this->id;
    }

    /**
     * Get all of the users that belong to the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status()
    {
        return $this->belongsTo(TaskStatus::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function stringStatus(): string
    {
        if ($this->invoice_id) {
            return '<h5><span class="badge badge-success">'.ctrans('texts.invoiced').'</span></h5>';
        }

        if ($this->status) {
            return '<h5><span class="badge badge-primary">' . $this->status?->name ?? ''; //@phpstan-ignore-line
        }

        return '';

    }

    public function calcStartTime()
    {
        $parts = json_decode($this->time_log) ?: [];

        if (count($parts)) {
            return Carbon::createFromTimeStamp((int)$parts[0][0])->timestamp;
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

    public function calcDuration(bool $billable = false)
    {
        $duration = 0;
        $parts = json_decode($this->time_log ?? '{}') ?: [];

        foreach ($parts as $part) {

            if($billable && isset($part[3]) && !$part[3]){
                continue;
            }

            $start_time = $part[0];

            if (count($part) == 1 || ! $part[1]) {
                $end_time = time();
            } else {
                $end_time = $part[1];
            }

            $duration += max($end_time - $start_time, 0);
        }

        return round($duration);
    }

    public function translate_entity()
    {
        return ctrans('texts.task');
    }

    public function getRate(): float
    {
        if (is_numeric($this->rate) && $this->rate > 0) {
            return $this->rate;
        }

        if ($this->project && $this->project->task_rate > 0) {
            return $this->project->task_rate;
        }

        if ($this->client) {
            return $this->client->getSetting('default_task_rate');
        }

        return $this->company->settings->default_task_rate ?? 0;
    }

    public function taskCompanyValue(): float
    {
        $client_currency = $this->client->getSetting('currency_id');
        $company_currency = $this->company->getSetting('currency_id');

        if ($client_currency != $company_currency) {
            $converter = new CurrencyApi();
            return $converter->convert($this->taskValue(), $client_currency, $company_currency);
        }

        return $this->taskValue();

    }

    public function getQuantity(): float
    {
        return round(($this->calcDuration(true) / 3600), 2);
    }

    public function logDuration(int $start_time, int $end_time)
    {
        return max(round(($end_time - $start_time) / 3600, 2), 0);
    }

    public function taskValue(): float
    {
        return round(($this->calcDuration(true) / 3600) * $this->getRate(), 2);
    }

    public function isRunning(): bool
    {

        $log = json_decode($this->time_log, true);

        $last = end($log);

        return  (is_array($last) && $last[1] === 0);

    }

    public function processLogs()
    {

        return
        collect(json_decode($this->time_log, true))->map(function ($log) {

            $parent_entity = $this->client ?? $this->company;

            if ($log[0]) {
                $log[0] = Carbon::createFromTimestamp((int)$log[0])->format($parent_entity->date_format().' H:i:s');
            }

            if ($log[1] && $log[1] != 0) {
                $log[1] = Carbon::createFromTimestamp((int)$log[1])->format($parent_entity->date_format().' H:i:s');
            } else {
                $log[1] = ctrans('texts.running');
            }

            return $log;
        })->toArray();
    }

    public function description(): string
    {
        $parent_entity = $this->client ?? $this->company;
        $time_format = $parent_entity->getSetting('military_time') ? "H:i:s" : "h:i:s A";

        $task_description =  collect(json_decode($this->time_log, true))
            ->filter(function ($log) {
                $billable = $log[3] ?? false;
                return $billable || $this->company->settings->allow_billable_task_items;
            })
            ->map(function ($log) use ($parent_entity, $time_format) {
                $interval_description = $log[2] ?? '';
                $hours = ctrans('texts.hours');

                $parts = [];
                $date_time = [];

                if ($this->company->invoice_task_datelog) {
                    $date_time[] = Carbon::createFromTimestamp((int)$log[0])
                        ->setTimeZone($this->company->timezone()->name)
                        ->format($parent_entity->date_format());
                }

                if ($this->company->invoice_task_timelog) {
                    $date_time[] = Carbon::createFromTimestamp((int)$log[0])
                        ->setTimeZone($this->company->timezone()->name)
                        ->format($time_format) . " - " .
                        Carbon::createFromTimestamp((int)$log[1])
                        ->setTimeZone($this->company->timezone()->name)
                        ->format($time_format);
                }

                if ($this->company->invoice_task_hours) {
                    $duration = $this->logDuration($log[0], $log[1]);

                    if ($this->company->use_comma_as_decimal_place) {
                        $duration = number_format($duration, 2, ',', '.');
                    }

                    $date_time[] = "{$duration} {$hours}";
                }

                $parts[] = implode(" • ", $date_time);

                if ($this->company->invoice_task_item_description && $this->company->settings->show_task_item_description && strlen($interval_description) > 1) {
                    $parts[] = $interval_description;
                }

                //need to return early if there is nothing, otherwise we end up injecting a blank new line.
                if (count($parts) == 1 && empty($parts[0])) {
                    return '';
                }

                return implode(PHP_EOL, $parts);
            })
            ->filter()//filters any empty strings.
            ->implode(PHP_EOL);

        $body = '';

        if (strlen($this->description ?? '') > 1) {
            $body .= $this->description. " ";
        }

        $body .= $task_description;

        return $body;
    }

    public function processLogsExpandedNotation()
    {

        return
        collect(json_decode($this->time_log ?? '{}', true))->map(function ($log) {

            $parent_entity = $this->client ?? $this->company;
            $logged = [];

            if ($log[0] && $log[1] != 0) {
                $duration = $log[1] - $log[0];
            } else {
                $duration = 0;
            }

            if ($log[0]) {
                $logged['start_date_raw'] = $log[0];
            }
            $logged['start_date'] = Carbon::createFromTimestamp((int)$log[0])->setTimeZone($this->company->timezone()->name)->format($parent_entity->date_format().' H:i:s');

            if ($log[1] && $log[1] != 0) {
                $logged['end_date_raw'] = $log[1];
                $logged['end_date'] = Carbon::createFromTimestamp((int)$log[1])->setTimeZone($this->company->timezone()->name)->format($parent_entity->date_format().' H:i:s');
            } else {
                $logged['end_date_raw'] = 0;
                $logged['end_date'] = ctrans('texts.running');
            }

            $logged['description'] =  $log[2] ?? '';
            $logged['billable'] = $log[3] ?? false;
            $logged['duration_raw'] = $duration;
            $logged['duration'] = gmdate("H:i:s", $duration);

            return $logged;

        })->toArray();
    }

    public function assignedCompanyUser()
    {
        if (!$this->assigned_user_id) {
            return false;
        }

        return CompanyUser::where('company_id', $this->company_id)->where('user_id', $this->assigned_user_id)->first();
    }
}
