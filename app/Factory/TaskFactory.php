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

namespace App\Factory;

use App\Models\Task;

class TaskFactory
{
    public static function create($company_id, $user_id): Task
    {
        $task = new Task();

        $task->description = '';
        //$task->rate = '';
        $task->company_id = $company_id;
        $task->user_id = $user_id;
        $task->time_log = '[]';
        $task->is_running = false;
        $task->is_deleted = false;
        $task->duration = 0;

        return $task;
    }
}
