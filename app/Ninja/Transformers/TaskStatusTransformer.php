<?php

namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\TaskStatus;

/**
 * @SWG\Definition(definition="Task", @SWG\Xml(name="Task"))
 */
class TaskStatusTransformer extends EntityTransformer
{
    public function __construct(Account $account)
    {
        parent::__construct($account);
    }

    public function transform(TaskStatus $taskStatus)
    {
        return array_merge($this->getDefaults($taskStatus), [
            'id' => (int) $taskStatus->public_id,
            'name' => $taskStatus->name ?: '',
            'sort_order' => (int) $taskStatus->sort_order + 1,
        ]);
    }
}
