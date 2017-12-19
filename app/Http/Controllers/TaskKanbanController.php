<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Project;
use App\Models\Client;

class TaskKanbanController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $tasks = Task::scope()->with(['project', 'client', 'task_status'])->orderBy('task_status_sort_order')->get();
        $statuses = TaskStatus::scope()->orderBy('sort_order')->get();
        $projects = Project::scope()->get();
        $clients = Client::scope()->get();

        // check initial statuses exist
        if (! $statuses->count()) {
            $statuses = collect([]);
            $defaults = [
                'backlog',
                'ready_to_do',
                'in_progress',
                'done',
            ];
            for ($i=0; $i<count($defaults); $i++) {
                $status = TaskStatus::createNew();
                $status->name = trans('texts.' . $defaults[$i]);
                $status->sort_order = $i;
                $status->save();
                $statuses[] = $status;
            }
        }

        $data = [
            'title' => trans('texts.kanban'),
            'statuses' => $statuses,
            'tasks' => $tasks,
            'clients' => $clients,
            'projects' => $projects,
        ];

        return view('tasks.kanban', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeStatus()
    {
        $status = TaskStatus::createNew();
        $status->fill(request()->all());
        $status->save();

        return response()->json($status);
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus($publicId)
    {
        $status = TaskStatus::scope($publicId)->firstOrFail();

        if ($status->sort_order != request('sort_order')) {
            $origSortOrder = $status->sort_order;
            $newSortOrder = request('sort_order');

            TaskStatus::scope()
                ->where('sort_order', '>', $origSortOrder)
                ->decrement('sort_order');

            TaskStatus::scope()
                ->where('sort_order', '>=', $newSortOrder)
                ->increment('sort_order');
        }

        $status->fill(request()->all());
        $status->save();

        return response()->json($status);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteStatus($publicId)
    {
        $status = TaskStatus::scope($publicId)->firstOrFail();
        $status->delete();

        TaskStatus::scope()
            ->where('sort_order', '>', $status->sort_order)
            ->decrement('sort_order');

        $firstStatus = TaskStatus::scope()
            ->orderBy('sort_order')
            ->first();

        // Move linked tasks to the end of the first status
        if ($firstStatus) {
            $firstCount = $firstStatus->tasks->count();
            Task::scope()
                ->where('task_status_id', '=', $status->id)
                ->increment('task_status_sort_order', $firstCount, [
                    'task_status_id' => $firstStatus->id
                ]);
        }

        return response()->json(['message' => RESULT_SUCCESS]);
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateTask($publicId)
    {
        $task = Task::scope($publicId)->firstOrFail();

        $origStatusId = $task->task_status_id;
        $origSortOrder = $task->task_status_sort_order;

        $newStatusId = request('task_status_id');
        $newSortOrder = request('task_status_sort_order');

        Task::scope()
            ->where('task_status_id', '=', $origStatusId)
            ->where('task_status_sort_order', '>', $origSortOrder)
            ->decrement('task_status_sort_order');

        Task::scope()
            ->where('task_status_id', '=', $newStatusId)
            ->where('task_status_sort_order', '>=', $newSortOrder)
            ->increment('task_status_sort_order');

        $task->task_status_id = TaskStatus::getPrivateId(request('task_status_id'));
        $task->task_status_sort_order = $newSortOrder;
        $task->save();

        return response()->json($task);
    }

}
