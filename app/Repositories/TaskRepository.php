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


    /**
     * Saves the task and its contacts.
     *
     * @param      array                         $data    The data
     * @param      \App\Models\Task              $task  The task
     *
     * @return     task|null  task Object
     */
    public function save(array $data, Task $task) : ?Task
    {
        $task->fill($data);
        $task->save();

        $task->number = empty($task->number) || !array_key_exists('number', $data) ? $this->getNextTaskNumber($task) : $data['number'];

        if (isset($data['description'])) {
            $task->description = trim($data['description']);
        }

        if (isset($data['status_sort_order'])) {
            $task->status_sort_order = $data['status_sort_order'];
        }

        if (isset($data['time_log'])) {
            $time_log = json_decode($data['time_log']);
        } elseif ($task->time_log) {
            $time_log = json_decode($task->time_log);
        } else {
            $time_log = [];
        }

        array_multisort($time_log);

        if (isset($data['action'])) {
            if ($data['action'] == 'start') {
                $task->is_running = true;
                $time_log[] = [strtotime('now'), false];
            } elseif ($data['action'] == 'resume') {
                $task->is_running = true;
                $time_log[] = [strtotime('now'), false];
            } elseif ($data['action'] == 'stop' && $task->is_running) {
                $time_log[count($time_log) - 1][1] = time();
                $task->is_running = false;
            } elseif ($data['action'] == 'offline') {
                $task->is_running = $data['is_running'] ? 1 : 0;
            }
        } elseif (isset($data['is_running'])) {
            $task->is_running = $data['is_running'] ? 1 : 0;
        }

        $task->time_log = json_encode($time_log);
        $task->start_time = $task->start_time ?: $task->calcStartTime();
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
