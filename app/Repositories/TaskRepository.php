<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Factory\TaskFactory;
use App\Models\Task;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

/**
 * TaskRepository.
 */
class TaskRepository extends BaseRepository
{
    use GeneratesCounter;

    public function __construct()
    {
    }

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return Task::class;
    }

    /**
     * Saves the task and its contacts.
     *
     * @param      array                           $data    The data
     * @param      \App\Models\task              $task  The task
     *
     * @return     task|null  task Object
     */
    public function save(array $data, Task $task) : ?Task
    {

        $task->fill($data);

        if(!$task->start_time)
            $task->start_time = $task->calcStartTime();

        $task->duration = $task->calcDuration();
        $task->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $task);
        }

        return $task;
    }

    /**
     * Store tasks in bulk.
     *
     * @param array $task
     * @return task|null
     */
    public function create($task): ?Task
    {
        return $this->save(
            $task,
            TaskFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }
}
