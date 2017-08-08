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
     */
    protected $availableIncludes = [
        'client',
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

    public function transform(Task $task)
    {
        return array_merge($this->getDefaults($task), [
            'id' => (int) $task->public_id,
            'description' => $task->description,
            'duration' => $task->getDuration(),
            'updated_at' => (int) $this->getTimestamp($task->updated_at),
            'archived_at' => (int) $this->getTimestamp($task->deleted_at),
            'invoice_id' => $task->invoice ? (int) $task->invoice->public_id : 0,
            'client_id' => $task->client ? (int) $task->client->public_id : 0,
            'project_id' => $task->project ? (int) $task->project->public_id : 0,
            'is_deleted' => (bool) $task->is_deleted,
            'time_log' => $task->time_log,
            'is_running' => (bool) $task->is_running,
        ]);
    }
}
