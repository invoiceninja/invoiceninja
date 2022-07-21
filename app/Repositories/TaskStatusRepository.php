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

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskStatus;

/**
 * Class for task status repository.
 */
class TaskStatusRepository extends BaseRepository
{

	public function delete($task_status)
	{
        $ts = TaskStatus::where('company_id', $task_status->company_id)
                                 ->first();

        $new_status = $ts ? $ts->id : null;

        Task::where('status_id', $task_status->id)
        ->where('company_id', $task_status->company_id)
        ->update(['status_id' => $new_status]);


        parent::delete($task_status);

        return $task_status;
    
	}

	public function archive($task_status)
	{

        $task_status = TaskStatus::where('id', $task_status->id)
                                 ->where('company_id', $task_status->company_id)
                                 ->first();

        $new_status = $task_status ? $task_status->id : null;
        
        Task::where('status_id', $task_status->id)
        ->where('company_id', $task_status->company_id)
        ->update(['status_id' => $new_status]);


        parent::archive($task_status);

        return $task_status;
    
	}
}
