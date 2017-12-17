<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskStatus;

class TaskKanbanController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $tasks = Task::scope()->get();
        $stauses = TaskStatus::scope()->get();

        // check initial statuses exist
        if (! $stauses->count()) {
            $stauses = [];
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
                $stauses[] = $status;
            }
        }

        $data = [
            'title' => trans('texts.kanban'),
            'statuses' => $stauses,
            'tasks' => $tasks,
        ];

        return view('tasks.kanban', $data);
    }

}
