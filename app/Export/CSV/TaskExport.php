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

namespace App\Export\CSV;

use App\Models\Task;
use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Timezone;
use App\Libraries\MultiDB;
use App\Models\DateFormat;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\TaskTransformer;
use Illuminate\Database\Eloquent\Builder;

class TaskExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'created_at';

    private string $date_format = 'YYYY-MM-DD';

    public Writer $csv;

    private Decorator $decorator;

    private array $storage_array = [];

    private array $storage_item_array = [];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new TaskTransformer();
        $this->decorator = new Decorator();
    }

    public function init(): Builder
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->date_format = DateFormat::find($this->company->settings->date_format_id)->format;
        ksort($this->entity_keys);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->task_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = Task::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', $this->input['include_deleted'] ?? false);

        $query = $this->addDateRange($query);
        
        $clients = &$this->input['client_id'];

        if($clients)
            $query = $this->addClientFilter($query, $clients);

        $document_attachments = &$this->input['document_email_attachment'];

        if($document_attachments) 
            $this->queueDocuments($query);

        return $query;

    }

    public function run()
    {

        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($entity) {
                  $this->buildRow($entity);
              });

        $this->csv->insertAll($this->storage_array);

        return $this->csv->toString();
    }


    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $query->cursor()
                ->each(function ($resource) {

                    $this->buildRow($resource);

                    foreach($this->storage_array as $row) {
                        $this->storage_item_array[] = $this->processMetaData($row, $resource);
                    }

                    $this->storage_array = [];
                });

        return array_merge(['columns' => $header], $this->storage_item_array);
    }

    private function buildRow(Task $task)
    {
        $entity = [];
        $transformed_entity = $this->entity_transformer->transform($task);
nlog($this->input['report_keys']);
        foreach (array_values($this->input['report_keys']) as $key) {
nlog($key);
            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'task' && array_key_exists($parts[1], $transformed_entity)) {
                $entity[$key] = $transformed_entity[$parts[1]];
            } elseif (array_key_exists($key, $transformed_entity)) {
                $entity[$key] = $transformed_entity[$key];
            } elseif (in_array($key, ['task.start_date', 'task.end_date', 'task.duration'])) {
                $entity[$key] = '';
            } else {
                $entity[$key] = $this->decorator->transform($key, $task);
            }

        }

        if (is_null($task->time_log) || (is_array(json_decode($task->time_log, 1)) && count(json_decode($task->time_log, 1)) == 0)) {
            $this->storage_array[] = $entity;
        } else {
            $this->iterateLogs($task, $entity);
        }

    }

    private function iterateLogs(Task $task, array $entity)
    {
        $timezone = Timezone::find($task->company->settings->timezone_id);
        $timezone_name = 'US/Eastern';

        if ($timezone) {
            $timezone_name = $timezone->name;
        }

        $logs = json_decode($task->time_log, 1);

        $date_format_default = 'Y-m-d';

        $date_format = DateFormat::find($task->company->settings->date_format_id);

        if ($date_format) {
            $date_format_default = $date_format->format;
        }

        foreach ($logs as $key => $item) {
            if (in_array('task.start_date', $this->input['report_keys']) || in_array('start_date', $this->input['report_keys'])) {
                $carbon_object = Carbon::createFromTimeStamp($item[0])->setTimezone($timezone_name);
                $entity['task.start_date'] = $carbon_object->format($date_format_default);
                $entity['task.start_time'] = $carbon_object->format('H:i:s');
            }

            if ((in_array('task.end_date', $this->input['report_keys']) || in_array('end_date', $this->input['report_keys'])) && $item[1] > 0) {
                $carbon_object = Carbon::createFromTimeStamp($item[1])->setTimezone($timezone_name);
                $entity['task.end_date'] = $carbon_object->format($date_format_default);
                $entity['task.end_time'] = $carbon_object->format('H:i:s');
            }

            if ((in_array('task.end_date', $this->input['report_keys']) || in_array('end_date', $this->input['report_keys'])) && $item[1] == 0) {
                $entity['task.end_date'] = ctrans('texts.is_running');
                $entity['task.end_time'] = ctrans('texts.is_running');
            }

            if (in_array('task.duration', $this->input['report_keys']) || in_array('duration', $this->input['report_keys'])) {
                $seconds = $task->calcDuration();
                $entity['task.duration'] = $seconds;
                $entity['task.duration_words'] =  $seconds > 86400 ? CarbonInterval::seconds($seconds)->locale($this->company->locale())->cascade()->forHumans() : now()->startOfDay()->addSeconds($seconds)->format('H:i:s');
            }

            $entity = $this->decorateAdvancedFields($task, $entity);

            $this->storage_array[] = $entity;

            $entity['task.start_date'] = '';
            $entity['task.start_time'] = '';
            $entity['task.end_date'] = '';
            $entity['task.end_time'] = '';
            $entity['task.duration'] = '';
            $entity['task.duration_words'] = '';

        }

    }
    
    /**
     * Add Task Status Filter
     *
     * @param  Builder $query
     * @param  string $status
     * @return Builder
     */
    protected function addTaskStatusFilter(Builder $query, string $status): Builder
    {
    
        $status_parameters = explode(',', $status);

        if (in_array('all', $status_parameters) || count($status_parameters) == 0) {
            return $query;
        }

        if (in_array('invoiced', $status_parameters)) {
            $query->whereNotNull('invoice_id');
        }

        if (in_array('uninvoiced', $status_parameters)) {
            $query->whereNull('invoice_id');
        }

        return $query;

    }

    private function decorateAdvancedFields(Task $task, array $entity): array
    {
        if (in_array('task.status_id', $this->input['report_keys'])) {
            $entity['task.status_id'] = $task->status()->exists() ? $task->status->name : '';
        }

        if (in_array('task.project_id', $this->input['report_keys'])) {
            $entity['task.project_id'] = $task->project()->exists() ? $task->project->name : '';
        }

        if (in_array('task.user_id', $this->input['report_keys'])) {
            $entity['task.user_id'] = $task->user ? $task->user->present()->name() : '';
        }

        if (in_array('task.assigned_user_id', $this->input['report_keys'])) {
            $entity['task.assigned_user_id'] = $task->assigned_user ? $task->assigned_user->present()->name() : '';
        }


        return $entity;
    }
}
