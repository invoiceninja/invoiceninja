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

use App\Factory\TaskFactory;
use App\Models\Task;
use App\Utils\Traits\GeneratesCounter;

/**
 * TaskRepository.
 */
class TaskRepository extends BaseRepository
{
    use GeneratesCounter;

    public $new_task = true;

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
        if($task->id)
            $this->new_task = false;

        $task->fill($data);
        $task->save();

        if($this->new_task && !$task->status_id)
            $this->setDefaultStatus($task);

        $task->number = empty($task->number) || !array_key_exists('number', $data) ? $this->getNextTaskNumber($task) : $data['number'];

        if (isset($data['description'])) {
            $task->description = trim($data['description']);
        }

        //todo i can't set it - i need to calculate it.
        if (isset($data['status_order'])) {
            $task->status_order = $data['status_order'];
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
        // $task->start_time = $task->start_time ?: $task->calcStartTime();
        // $task->duration = $task->calcDuration();

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

    private function setDefaultStatus(Task $task)
    {
        $first_status = $task->company->task_statuses()
                              ->whereNull('deleted_at')
                              ->orderBy('id','asc')
                              ->first();

        if($first_status)
            return $first_status->id;

        return null;
    }

    /**
     * Sorts the task status order IF the old status has changed between requests
     *     
     * @param  stdCLass $old_task The old task object
     * @param  Task     $new_task The new Task model
     * @return void
     */
    public function sortStatuses($old_task, $new_task)
    {

        if(!$new_task->project()->exists())
            return;

        $index = $new_task->status_order;

        $tasks = $new_task->project->tasks->reject(function ($task)use($new_task){
            return $task->id == $new_task->id;
        });

        $sorted_tasks = $tasks->filter(function($task, $key)use($index){
            return $key < $index;
        })->push($new_task)->merge($tasks->filter(function($task, $key)use($index){
            return $key >= $index;
        }))->each(function ($item,$key){
            $item->status_order = $key;
            $item->save();
        });

    }
}
