<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\TaskStatus;

class TaskStatusFactory
{
    public static function create(int $company_id, int $user_id): TaskStatus
    {
        $task_status = new TaskStatus();
        $task_status->user_id = $user_id;
        $task_status->company_id = $company_id;
        $task_status->name = '';
        $task_status->color = '';
        $task_status->status_order = 9999;

        return $task_status;
    }
}
