<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Models\Task;

/**
 * Class for task status repository.
 */
class TaskStatusRepository extends BaseRepository
{

	public function delete($task_status)
	{

        Task::where('status_id', $task_status->id)
        ->where('company_id', $task_status->company_id)
        ->update(['status_id' => null]);


        parent::delete($task_status);

        return $task_status;
    
	}

	public function archive($task_status)
	{

        Task::where('status_id', $task_status->id)
        ->where('company_id', $task_status->company_id)
        ->update(['status_id' => null]);


        parent::archive($task_status);

        return $task_status;
    
	}
}
