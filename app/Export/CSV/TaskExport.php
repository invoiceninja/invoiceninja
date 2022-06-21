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

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\DateFormat;
use App\Models\Task;
use App\Models\Timezone;
use App\Transformers\TaskTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class TaskExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private $entity_transformer;

    protected $date_key = 'created_at';

    private string $date_format = 'YYYY-MM-DD';

    protected array $entity_keys = [
        'start_date' => 'start_date',
        'end_date' => 'end_date',
        'duration' => 'duration',
        'rate' => 'rate',
        'number' => 'number',
        'description' => 'description',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'status' => 'status_id',
        'project' => 'project_id',
        'invoice' => 'invoice_id',
        'client' => 'client_id',
    ];

    private array $decorate_keys = [
        'status',
        'project',
        'client',
        'invoice',
        'start_date',
        'end_date',
        'duration',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new TaskTransformer();
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->date_format = DateFormat::find($this->company->settings->date_format_id)->format;

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        ksort($this->entity_keys);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = Task::query()->where('company_id', $this->company->id)->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($entity) {
                  $this->buildRow($entity);
              });

        return $this->csv->toString();
    }

    private function buildRow(Task $task)
    {
        $entity = [];
        $transformed_entity = $this->entity_transformer->transform($task);

        if (is_null($task->time_log) || (is_array(json_decode($task->time_log, 1)) && count(json_decode($task->time_log, 1)) == 0)) {
            foreach (array_values($this->input['report_keys']) as $key) {
                $keyval = array_search($key, $this->entity_keys);

                if (array_key_exists($key, $transformed_entity)) {
                    $entity[$keyval] = $transformed_entity[$key];
                } else {
                    $entity[$keyval] = '';
                }
            }

            $entity['start_date'] = '';
            $entity['end_date'] = '';
            $entity['duration'] = '';

            $entity = $this->decorateAdvancedFields($task, $entity);

            ksort($entity);
            $this->csv->insertOne($entity);
        } elseif (is_array(json_decode($task->time_log, 1)) && count(json_decode($task->time_log, 1)) > 0) {
            foreach (array_values($this->input['report_keys']) as $key) {
                $keyval = array_search($key, $this->entity_keys);

                if (array_key_exists($key, $transformed_entity)) {
                    $entity[$keyval] = $transformed_entity[$key];
                } else {
                    $entity[$keyval] = '';
                }
            }

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
            if (in_array('start_date', $this->input['report_keys'])) {
                $entity['start_date'] = Carbon::createFromTimeStamp($item[0])->setTimezone($timezone_name)->format($date_format_default);
            }

            if (in_array('end_date', $this->input['report_keys']) && $item[1] > 0) {
                $entity['end_date'] = Carbon::createFromTimeStamp($item[1])->setTimezone($timezone_name)->format($date_format_default);
            }

            if (in_array('end_date', $this->input['report_keys']) && $item[1] == 0) {
                $entity['end_date'] = ctrans('texts.is_running');
            }

            if (in_array('duration', $this->input['report_keys'])) {
                $entity['duration'] = $task->calcDuration();
            }

            if (! array_key_exists('duration', $entity)) {
                $entity['duration'] = '';
            }

            if (! array_key_exists('start_date', $entity)) {
                $entity['start_date'] = '';
            }

            if (! array_key_exists('end_date', $entity)) {
                $entity['end_date'] = '';
            }

            $entity = $this->decorateAdvancedFields($task, $entity);

            ksort($entity);
            $this->csv->insertOne($entity);

            unset($entity['start_date']);
            unset($entity['end_date']);
            unset($entity['duration']);
        }
    }

    private function decorateAdvancedFields(Task $task, array $entity) :array
    {
        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $task->status()->exists() ? $task->status->name : '';
        }

        if (in_array('project_id', $this->input['report_keys'])) {
            $entity['project'] = $task->project()->exists() ? $task->project->name : '';
        }

        if (in_array('client_id', $this->input['report_keys'])) {
            $entity['client'] = $task->client ? $task->client->present()->name() : '';
        }

        if (in_array('invoice_id', $this->input['report_keys'])) {
            $entity['invoice'] = $task->invoice ? $task->invoice->number : '';
        }

        return $entity;
    }
}
