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

namespace App\Import\Transformer\Csv;

use App\Import\Transformer\BaseTransformer;

/**
 * Class TaskTransformer.
 */
class TaskTransformer extends BaseTransformer
{
    private int $stubbed_timestamp = 0;
    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($task_items_data)
    {
        $this->stubbed_timestamp = time();

        $task_data = reset($task_items_data);

        $clientId = $this->getClient(
            $this->getString($task_data, 'client.name'),
            $this->getString($task_data, 'client.email')
        );

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($task_data, 'task.number'),
            'user_id' => $this->getString($task_data, 'task.user_id'),
            'client_id' => $clientId,
            'project_id' => $this->getProjectId($task_data['project.name'], $clientId),
            'description' => $this->getString($task_data, 'task.description'),
            'status' => $this->getTaskStatusId($task_data),
            'custom_value1' => $this->getString($task_data, 'task.custom_value1'),
            'custom_value2' => $this->getString($task_data, 'task.custom_value2'),
            'custom_value3' => $this->getString($task_data, 'task.custom_value3'),
            'custom_value4' => $this->getString($task_data, 'task.custom_value4'),
        ];

        $time_log = collect($task_items_data)
                            ->map(function ($item) {

                                return $this->parseLog($item);

                            })->toJson();

        nlog($time_log);

        $transformed['time_log'] = $time_log;

        return $transformed;
    }

    private function parseLog($item): array
    {
        $start_date = false;
        $end_date = false;

        $notes = $item['task.notes'] ?? '';
        $is_billable = $item['task.is_billable'] ?? false;

        if(isset($item['start_date']) &&
        isset($item['end_date'])) {
            $start_date = $this->resolveStartDate($item);
            $end_date = $this->resolveEndDate($item);
        } elseif(isset($item['duration'])) {
            $duration =  strtotime($item['duration']) - strtotime('TODAY');
            $start_date = $this->stubbed_timestamp;
            $end_date = $this->stubbed_timestamp + $duration;
            $this->stubbed_timestamp++;
        } else {
            return [];
        }

        return [$start_date, $end_date, $notes, $is_billable];
    }

    private function resolveStartDate($item)
    {

        $stub_start_date = $item['start_date'] . ' ' . isset($item['start_time']) ?? '';

        try {
            $stub_start_date = \Carbon\Carbon::parse($stub_start_date);
            $this->stubbed_timestamp = $stub_start_date->timestamp;
            return $stub_start_date->timestamp;
        } catch (\Exception $e) {
            return $this->stubbed_timestamp;
        }
        
    }

    private function resolveEndDate($item)
    {

        $stub_start_date = $item['end_date'] . ' ' . isset($item['end_time']) ?? '';

        try {
            $stub_start_date = \Carbon\Carbon::parse($stub_start_date);

            if($stub_start_date->timestamp == $this->stubbed_timestamp) {
                $this->stubbed_timestamp++;
                return $this->stubbed_timestamp;
            }

            $this->stubbed_timestamp = $stub_start_date->timestamp++;
            return $stub_start_date->timestamp;
        } catch (\Exception $e) {
            return $this->stubbed_timestamp++;
        }
        
    }

}
