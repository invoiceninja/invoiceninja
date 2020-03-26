<?php

namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Client;
use App\Models\Task;

/**
 * @SWG\Definition(definition="Task", @SWG\Xml(name="Task"))
 */
class TaskTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="amount", type="number", format="float", example=10, readOnly=true)
     * @SWG\Property(property="invoice_id", type="integer", example=1)
     * @SWG\Property(property="recurring_invoice_id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="client_id", type="integer", example=1)
     * @SWG\Property(property="project_id", type="integer", example=1)
     * @SWG\Property(property="is_deleted", type="boolean", example=false, readOnly=true)
     * @SWG\Property(property="time_log", type="string", example="Time Log")
     * @SWG\Property(property="is_running", type="boolean", example=false)
     * @SWG\Property(property="custom_value1", type="string", example="Custom Value")
     * @SWG\Property(property="custom_value2", type="string", example="Custom Value")
     */
    protected $availableIncludes = [
        'client',
        'project',
    ];

    public function __construct(Account $account)
    {
        parent::__construct($account);
    }

    public function includeClient(Task $task)
    {
        if ($task->client) {
            $transformer = new ClientTransformer($this->account, $this->serializer);

            return $this->includeItem($task->client, $transformer, 'client');
        } else {
            return null;
        }
    }

    public function includeProject(Task $task)
    {
        if ($task->project) {
            $transformer = new ProjectTransformer($this->account, $this->serializer);

            return $this->includeItem($task->project, $transformer, 'project');
        } else {
            return null;
        }
    }

    public function transform(Task $task)
    {
        return array_merge($this->getDefaults($task), [
            'id' => (int) $task->public_id,
            'description' => $task->description ?: '',
            'duration' => $task->getDuration() ?: 0,
            'updated_at' => (int) $this->getTimestamp($task->updated_at),
            'archived_at' => (int) $this->getTimestamp($task->deleted_at),
            'invoice_id' => $task->invoice ? (int) $task->invoice->public_id : 0,
            'client_id' => $task->client ? (int) $task->client->public_id : 0,
            'project_id' => $task->project ? (int) $task->project->public_id : 0,
            'is_deleted' => (bool) $task->is_deleted,
            'time_log' => $task->time_log ?: '',
            'is_running' => (bool) $task->is_running,
            'custom_value1' => $task->custom_value1 ?: '',
            'custom_value2' => $task->custom_value2 ?: '',
            'task_status_id' => $task->task_status ? (int) $task->task_status->public_id : 0,
            'task_status_sort_order' => (int) $task->task_status_sort_order,
        ]);
    }
}
