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

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = Task::query()->where('company_id', $this->company->id)->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($entity){

                $this->buildRow($entity); 

        });

        return $this->csv->toString(); 

    }

    private function buildRow(Task $task) 
    {

        $entity = [];
        $transformed_entity = $this->entity_transformer->transform($task);

        if(is_null($task->time_log) || (is_array(json_decode($task->time_log,1)) && count(json_decode($task->time_log,1)) == 0)){

            foreach(array_values($this->input['report_keys']) as $key){

                if(array_key_exists($key, $transformed_entity))
                    $entity[$key] = $transformed_entity[$key];
                else
                    $entity[$key] = '';            
            }

            $entity = $this->decorateAdvancedFields($task, $entity);

            $this->csv->insertOne($entity);
        }
        elseif(is_array(json_decode($task->time_log,1)) && count(json_decode($task->time_log,1)) > 0) {

            foreach(array_values($this->input['report_keys']) as $key){

                if(array_key_exists($key, $transformed_entity))
                    $entity[$key] = $transformed_entity[$key];
                else
                    $entity[$key] = '';            
            }

            $entity = $this->decorateAdvancedFields($task, $entity);

            $this->iterateLogs($task, $entity);
        }

    }

    private function iterateLogs(Task $task, array $entity)
    {
        $timezone = Timezone::find($task->company->settings->timezone_id);
        $timezone_name = 'US/Eastern';

        if($timezone)
            $timezone_name = $timezone->name;

        $logs = json_decode($task->time_log,1);

        foreach($logs as $key => $item)
        {

            if(in_array("start_date",$this->input['report_keys'])){
                $entity['start_date'] = Carbon::createFromTimeStamp($item[0])->setTimezone($timezone_name);
                nlog("start date" . $entity['start_date']);
            }

            if(in_array("end_date",$this->input['report_keys']) && $item[1] > 0){
                $entity['end_date'] = Carbon::createFromTimeStamp($item[1])->setTimezone($timezone_name);
                nlog("start date" . $entity['end_date']);
            }

            if(in_array("end_date",$this->input['report_keys']) && $item[1] == 0){
                $entity['end_date'] = ctrans('texts.is_running');
                nlog("start date" . $entity['end_date']);
            }

            if(in_array("duration",$this->input['report_keys'])){
                $entity['duration'] = $task->calcDuration();
                nlog("duration" . $entity['duration']);
            }

            $this->csv->insertOne($entity);

            unset($entity['start_date']);
            unset($entity['end_date']);
            unset($entity['duration']);
        }

    }

    private function decorateAdvancedFields(Task $task, array $entity) :array
    {

        if(array_key_exists('status_id', $entity))
            $entity['status_id'] = $task->status()->exists() ? $task->status->name : '';

        if(array_key_exists('project_id', $entity))
            $entity['project_id'] = $task->project()->exists() ? $task->project->name : '';

        if(array_key_exists('client_id', $entity))
            $entity['client_id'] = $task->client->present()->name();



        return $entity;
    }

}
